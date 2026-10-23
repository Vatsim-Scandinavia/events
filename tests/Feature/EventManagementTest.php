<?php

namespace Tests\Feature;

use App\Enums\RepeatInterval;
use App\Enums\StaffingMode;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Fir;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class EventManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-01 12:00', 'UTC'));
        $this->staff = User::factory()->staff()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'fir_id' => Fir::factory()->create()->id,
            'name' => 'Copenhagen Fly-in',
            'event_type_id' => EventType::factory()->create()->id,
            'description' => 'Full coverage at Kastrup.',
            'airports' => ['ekch', ' ekbi '],
            'routes' => [['from' => 'ekbi', 'to' => 'EKCH', 'route' => 'tudlo  dct']],
            'booking_url' => 'https://example.com/book',
            'briefing_url' => null,
            'starts_at' => '2026-11-10 22:00',
            'ends_at' => '2026-11-11 02:00',
            'repeats' => 'none',
            ...$overrides,
        ];
    }

    /**
     * @return list<string>
     */
    private function dates(Event $event): array
    {
        return $event->occurrences()->get()
            ->map(fn ($occurrence) => $occurrence->starts_at->format('Y-m-d H:i').'–'.$occurrence->ends_at->format('H:i'))
            ->all();
    }

    public function test_only_staff_can_open_the_event_pages(): void
    {
        $event = Event::factory()->create();

        $this->get('/events/create')->assertRedirect('/auth/vatsim');
        $this->actingAs(User::factory()->create())->get('/events/create')->assertForbidden();
        $this->actingAs(User::factory()->create())->delete("/events/{$event->id}")->assertForbidden();

        $this->actingAs($this->staff)->get('/events/create')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Events/Form')
            ->where('event', null)
        );
        $this->actingAs($this->staff)->get("/events/{$event->id}")->assertInertia(fn (Assert $page) => $page->where('canEdit', true));
    }

    public function test_staff_choose_the_fir_that_runs_an_event(): void
    {
        $copenhagen = Fir::factory()->create(['icao' => 'EKDK', 'name' => 'Copenhagen']);
        $sweden = Fir::factory()->create(['icao' => 'ESAA', 'name' => 'Sweden']);

        $this->actingAs($this->staff)->get('/events/create')->assertInertia(fn (Assert $page) => $page
            ->where('firs', [
                ['id' => $copenhagen->id, 'icao' => 'EKDK', 'name' => 'Copenhagen'],
                ['id' => $sweden->id, 'icao' => 'ESAA', 'name' => 'Sweden'],
            ])
        );

        $this->actingAs($this->staff)->post('/events', $this->payload(['fir_id' => $copenhagen->id]))->assertSessionHasNoErrors();
        $event = Event::query()->sole();
        $this->assertTrue($event->fir->is($copenhagen));

        $this->actingAs($this->staff)->get("/events/{$event->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('event.firId', $copenhagen->id)
        );

        $this->actingAs($this->staff)->put("/events/{$event->id}", $this->payload(['fir_id' => $sweden->id]))->assertSessionHasNoErrors();
        $this->assertTrue($event->fresh()->fir->is($sweden));

        $this->get("/events/{$event->id}")->assertInertia(fn (Assert $page) => $page
            ->where('event.fir', ['icao' => 'ESAA', 'name' => 'Sweden'])
        );
    }

    public function test_staff_can_mark_suggested_routes_as_mandatory(): void
    {
        $this->actingAs($this->staff)->post('/events', $this->payload(['routes' => [
            ['from' => 'EKBI', 'to' => 'EKCH', 'route' => 'TUDLO', 'mandatory' => '1'],
            ['from' => 'EKCH', 'to' => 'EKBI', 'route' => 'SIMEG', 'mandatory' => 'false'],
            ['from' => 'EKAH', 'to' => 'EKCH', 'route' => 'KOR'],
        ]]))->assertSessionHasNoErrors();

        $event = Event::query()->sole();
        $this->assertSame([true, false, false], array_column($event->routes, 'mandatory'));

        $this->get("/events/{$event->id}")->assertInertia(fn (Assert $page) => $page
            ->where('event.routes.0.mandatory', true)
            ->where('event.routes.1.mandatory', false)
        );
    }

    public function test_staff_pick_a_type_from_the_admin_list_or_leave_it_general(): void
    {
        $flyIn = EventType::query()->where('name', 'Fly-in')->sole();

        $this->actingAs($this->staff)->get('/events/create')->assertInertia(fn (Assert $page) => $page
            ->where('types', fn ($types) => collect($types)->contains(['id' => $flyIn->id, 'name' => 'Fly-in']))
        );

        $this->actingAs($this->staff)->post('/events', $this->payload(['event_type_id' => 999]))->assertSessionHasErrors('event_type_id');
        $this->assertDatabaseCount('events', 0);

        $this->actingAs($this->staff)->post('/events', $this->payload(['event_type_id' => $flyIn->id]))->assertSessionHasNoErrors();
        $event = Event::query()->sole();
        $this->get("/events/{$event->id}")->assertInertia(fn (Assert $page) => $page->where('event.type', 'Fly-in'));

        $this->actingAs($this->staff)->put("/events/{$event->id}", $this->payload(['event_type_id' => '']))->assertSessionHasNoErrors();
        $this->get("/events/{$event->id}")->assertInertia(fn (Assert $page) => $page->where('event.type', 'General'));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidFirs(): array
    {
        return [
            'no FIR' => [null],
            'a FIR that does not exist' => [999],
        ];
    }

    #[DataProvider('invalidFirs')]
    public function test_an_event_needs_a_fir_that_exists(mixed $fir): void
    {
        $this->actingAs($this->staff)
            ->post('/events', $this->payload(['fir_id' => $fir]))
            ->assertSessionHasErrors(['fir_id' => 'Pick the FIR that runs the event.']);

        $this->assertDatabaseCount('events', 0);
    }

    public function test_staff_can_create_a_weekly_event(): void
    {
        $this->actingAs($this->staff)
            ->post('/events', $this->payload(['repeats' => 'weekly', 'repeat_ends' => 'count', 'repeat_count' => 3]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $event = Event::sole();

        $this->assertSame(['EKCH', 'EKBI'], $event->airports);
        $this->assertSame([['from' => 'EKBI', 'to' => 'EKCH', 'route' => 'TUDLO DCT', 'mandatory' => false]], $event->routes);
        $this->assertSame('2026-11-10 22:00', $event->starts_at->format('Y-m-d H:i'));
        $this->assertSame(3, $event->repeat_count);
        $this->assertSame(
            ['2026-11-10 22:00–02:00', '2026-11-17 22:00–02:00', '2026-11-24 22:00–02:00'],
            $this->dates($event),
        );

        $this->get("/events/{$event->id}")->assertInertia(fn (Assert $page) => $page->where('event.repeats', 'Every week on Tuesday'));
    }

    public function test_monthly_events_keep_their_weekday(): void
    {
        // 14 November is the second Saturday; the repeats run until the end of January.
        $this->actingAs($this->staff)->post('/events', $this->payload([
            'starts_at' => '2026-11-14 22:00',
            'ends_at' => '2026-11-15 02:00',
            'repeats' => 'monthly_weekday',
            'repeat_ends' => 'until',
            'repeat_until' => '2027-01-31',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(['2026-11-14', '2026-12-12', '2027-01-09'], Event::sole()->occurrences->map(fn ($o) => $o->starts_at->toDateString())->all());

        // 28 November is the last Saturday of the month.
        $this->actingAs($this->staff)->post('/events', $this->payload([
            'name' => 'Last Saturday',
            'starts_at' => '2026-11-28 22:00',
            'ends_at' => '2026-11-29 02:00',
            'repeats' => 'monthly_last_weekday',
            'repeat_ends' => 'count',
            'repeat_count' => 3,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(
            ['2026-11-28', '2026-12-26', '2027-01-30'],
            Event::where('name', 'Last Saturday')->sole()->occurrences->map(fn ($o) => $o->starts_at->toDateString())->all(),
        );
    }

    public function test_the_schedule_is_validated(): void
    {
        $this->actingAs($this->staff)
            ->post('/events', $this->payload(['starts_at' => '2026-10-30 22:00', 'ends_at' => '2026-10-31 02:00']))
            ->assertSessionHasErrors('starts_at');

        $this->actingAs($this->staff)
            ->post('/events', $this->payload(['ends_at' => '2026-11-10 21:00']))
            ->assertSessionHasErrors('ends_at');

        $this->actingAs($this->staff)
            ->post('/events', $this->payload(['ends_at' => '2026-11-14 02:00']))
            ->assertSessionHasErrors('ends_at');

        $this->actingAs($this->staff)
            ->post('/events', $this->payload(['repeats' => 'weekly']))
            ->assertSessionHasErrors('repeat_ends');

        $this->actingAs($this->staff)
            ->post('/events', $this->payload(['repeats' => 'weekly', 'repeat_ends' => 'until', 'repeat_until' => '2026-11-01']))
            ->assertSessionHasErrors('repeat_until');

        $this->actingAs($this->staff)
            ->post('/events', $this->payload(['repeats' => 'weekly', 'repeat_ends' => 'until', 'repeat_until' => '2028-12-31']))
            ->assertSessionHasErrors('repeat_until');

        $this->actingAs($this->staff)
            ->post('/events', $this->payload(['repeats' => 'weekly', 'repeat_ends' => 'count', 'repeat_count' => 60]))
            ->assertSessionHasErrors('repeat_count');

        // There's no fifth Sunday in most months.
        $this->actingAs($this->staff)
            ->post('/events', $this->payload(['starts_at' => '2026-11-29 22:00', 'ends_at' => '2026-11-30 02:00', 'repeats' => 'monthly_weekday', 'repeat_ends' => 'count', 'repeat_count' => 3]))
            ->assertSessionHasErrors('repeats');

        $this->actingAs($this->staff)
            ->post('/events', $this->payload(['airports' => ['Kastrup']]))
            ->assertSessionHasErrors('airports.0');

        $this->assertSame(0, Event::count());
    }

    public function test_the_schedule_preview_shows_which_dates_move_and_which_go(): void
    {
        $this->actingAs($this->staff)
            ->post('/events', $this->payload(['starts_at' => '2026-11-10 18:00', 'ends_at' => '2026-11-10 22:00', 'repeats' => 'weekly', 'repeat_ends' => 'count', 'repeat_count' => 3]))
            ->assertSessionHasNoErrors();
        $event = Event::sole();
        [$first, $second, $third] = $event->occurrences()->pluck('id')->all();

        // A day later and one date fewer: the first two dates move, the third is left out.
        $this->actingAs($this->staff)
            ->postJson("/events/{$event->id}/schedule", ['starts_at' => '2026-11-11 18:00', 'ends_at' => '2026-11-11 22:00', 'repeats' => 'weekly', 'repeat_ends' => 'count', 'repeat_count' => 2])
            ->assertOk()
            ->assertExactJson([
                'dates' => [['date' => '2026-11-11', 'id' => $first], ['date' => '2026-11-18', 'id' => $second]],
                'removed' => [$third],
            ]);

        $this->assertSame(['2026-11-10 18:00–22:00', '2026-11-17 18:00–22:00', '2026-11-24 18:00–22:00'], $this->dates($event));

        // A new event has nothing to move.
        $this->actingAs($this->staff)
            ->postJson('/events/schedule', ['starts_at' => '2026-11-10 18:00', 'ends_at' => '2026-11-10 22:00', 'repeats' => 'none'])
            ->assertExactJson(['dates' => [['date' => '2026-11-10', 'id' => null]], 'removed' => []]);
    }

    public function test_the_schedule_preview_reports_what_saving_would_refuse(): void
    {
        $this->actingAs($this->staff)
            ->postJson('/events/schedule', ['starts_at' => '2026-10-30 18:00', 'ends_at' => '2026-10-30 22:00', 'repeats' => 'none'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');

        $this->actingAs($this->staff)
            ->postJson('/events/schedule', ['starts_at' => '2026-11-10 18:00', 'ends_at' => '2026-11-10 22:00', 'repeats' => 'weekly', 'repeat_ends' => 'until', 'repeat_until' => '2028-12-31'])
            ->assertJsonValidationErrors('repeat_until');
    }

    public function test_moving_a_date_moves_its_roster_and_interest(): void
    {
        $event = Event::factory()->withOpenInterest()->create([
            'starts_at' => Carbon::parse('2026-11-10 18:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-10 22:00', 'UTC'),
        ]);
        $occurrence = $event->occurrences()->sole();
        $position = $event->positions()->create(['callsign' => 'EKCH_TWR']);
        $controller = User::factory()->create();
        $occurrence->rosterEntries()->create([
            'event_position_id' => $position->id,
            'user_id' => $controller->id,
            'starts_at' => Carbon::parse('2026-11-10 19:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-10 21:00', 'UTC'),
        ]);
        $interest = $occurrence->interests()->create(['user_id' => $controller->id]);
        $interest->windows()->create(['starts_at' => Carbon::parse('2026-11-10 18:00', 'UTC'), 'ends_at' => Carbon::parse('2026-11-10 22:00', 'UTC')]);

        $this->actingAs($this->staff)->put("/events/{$event->id}", $this->payload([
            'starts_at' => '2026-11-12 17:00',
            'ends_at' => '2026-11-12 20:30',
        ]))->assertSessionHasNoErrors();

        $occurrence->refresh();
        $this->assertSame('2026-11-12 17:00', $occurrence->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-11-12 20:30', $occurrence->ends_at->format('Y-m-d H:i'));
        $this->assertSame('2026-11-12 18:00', $occurrence->rosterEntries()->sole()->starts_at->format('Y-m-d H:i'));
        // Availability moves along and is trimmed to the shorter event.
        $window = $interest->windows()->sole();
        $this->assertSame('2026-11-12 17:00', $window->starts_at->format('Y-m-d H:i'));
        $this->assertSame('2026-11-12 20:30', $window->ends_at->format('Y-m-d H:i'));
        $this->assertSame('2026-11-12 17:00', $event->fresh()->starts_at->format('Y-m-d H:i'));
    }

    public function test_the_event_cannot_get_shorter_than_its_shifts(): void
    {
        $event = Event::factory()->withShifts()->create([
            'starts_at' => Carbon::parse('2026-11-10 18:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-10 22:00', 'UTC'),
        ]);
        $occurrence = $event->occurrences()->sole();
        $event->positions()->create(['callsign' => 'EKCH_TWR'])->shifts()->create(['starts_offset' => 120, 'ends_offset' => 240]);

        $this->actingAs($this->staff)->put("/events/{$event->id}", $this->payload([
            'starts_at' => '2026-11-10 18:00',
            'ends_at' => '2026-11-10 21:00',
        ]))->assertSessionHasErrors('ends_at');

        $this->assertSame('22:00', $occurrence->fresh()->ends_at->format('H:i'));
    }

    public function test_changing_a_series_under_way_keeps_past_dates_and_staffing(): void
    {
        // Weekly from Saturday 24 October; two dates have been, two are coming up.
        $event = Event::factory()->withShifts()->create([
            'starts_at' => Carbon::parse('2026-10-24 18:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-10-24 21:00', 'UTC'),
            'repeats' => 'weekly',
            'repeat_count' => 4,
        ]);
        foreach (['2026-10-31', '2026-11-07', '2026-11-14'] as $day) {
            $event->occurrences()->create(['starts_at' => Carbon::parse("{$day} 18:00", 'UTC'), 'ends_at' => Carbon::parse("{$day} 21:00", 'UTC')]);
        }
        [$first, $second, $third, $fourth] = $event->occurrences()->get()->all();
        $shift = $event->positions()->create(['callsign' => 'EKCH_TWR'])->shifts()->create(['starts_offset' => 0, 'ends_offset' => 60]);
        $booking = $shift->bookings()->create(['event_occurrence_id' => $fourth->id, 'user_id' => User::factory()->create()->id]);

        // Every 2 weeks from the same first date, an hour later: 24 Oct, 7 Nov, 21 Nov, 5 Dec.
        $this->actingAs($this->staff)->put("/events/{$event->id}", $this->payload([
            'starts_at' => '2026-10-24 19:00',
            'ends_at' => '2026-10-24 22:00',
            'repeats' => 'biweekly',
            'repeat_ends' => 'count',
            'repeat_count' => 4,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(
            ['2026-10-24 18:00–21:00', '2026-10-31 18:00–21:00', '2026-11-07 19:00–22:00', '2026-11-21 19:00–22:00', '2026-12-05 19:00–22:00'],
            $this->dates($event),
        );
        // 7 November stays itself, and 14 November (with its booking) moves to 21 November.
        $this->assertSame('2026-11-07', $third->fresh()->starts_at->toDateString());
        $this->assertSame('2026-11-21', $fourth->fresh()->starts_at->toDateString());
        $this->assertModelExists($booking);
        $this->assertModelExists($first);
        $this->assertModelExists($second);

        // Ending sooner removes the upcoming dates that are no longer in the schedule.
        $this->actingAs($this->staff)->put("/events/{$event->id}", $this->payload([
            'starts_at' => '2026-10-24 19:00',
            'ends_at' => '2026-10-24 22:00',
            'repeats' => 'biweekly',
            'repeat_ends' => 'until',
            'repeat_until' => '2026-11-10',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(['2026-10-24 18:00–21:00', '2026-10-31 18:00–21:00', '2026-11-07 19:00–22:00'], $this->dates($event));
        $this->assertModelMissing($booking);
    }

    public function test_overnight_events_can_run_past_a_day(): void
    {
        $this->actingAs($this->staff)
            ->post('/events', $this->payload(['starts_at' => '2026-11-14 12:00', 'ends_at' => '2026-11-15 18:00']))
            ->assertSessionHasNoErrors();

        $this->assertSame(['2026-11-14 12:00–18:00'], $this->dates(Event::sole()));
        $this->assertSame('2026-11-15 18:00', Event::sole()->occurrences()->sole()->ends_at->format('Y-m-d H:i'));
    }

    public function test_repeating_with_no_end_creates_dates_six_months_ahead_and_tops_them_up(): void
    {
        $this->actingAs($this->staff)
            ->post('/events', $this->payload(['repeats' => 'weekly', 'repeat_ends' => 'never']))
            ->assertSessionHasNoErrors();

        $event = Event::sole();
        $this->assertSame(RepeatInterval::Weekly, $event->repeats);
        $this->assertNull($event->repeat_count);
        $this->assertNull($event->repeat_until);
        // Tuesdays from 10 November up to 1 May, six months from now.
        $this->assertSame('2027-04-27', $event->occurrences()->get()->last()->starts_at->toDateString());
        $this->assertCount(25, $event->occurrences()->get());

        $this->travelTo(Carbon::parse('2026-12-01 12:00', 'UTC'));
        $this->artisan('events:extend-repeats')->assertSuccessful();

        $this->assertSame('2027-06-01', $event->occurrences()->get()->last()->starts_at->toDateString());
        $this->assertCount(30, $event->occurrences()->get());
    }

    public function test_repeats_in_a_local_time_zone_keep_their_local_time_across_daylight_saving(): void
    {
        // Copenhagen moves to summer time on 28 March 2027: 19:00 local is 18:00z before and 17:00z after.
        $schedule = [
            'timezone' => 'Europe/Copenhagen',
            'starts_at' => '2027-03-20 19:00',
            'ends_at' => '2027-03-20 22:00',
            'repeats' => 'weekly',
            'repeat_ends' => 'count',
            'repeat_count' => 3,
        ];

        $this->actingAs($this->staff)->post('/events', $this->payload($schedule))->assertSessionHasNoErrors();

        $event = Event::sole();
        $this->assertSame('Europe/Copenhagen', $event->timezone);
        $this->assertSame(['2027-03-20 18:00–21:00', '2027-03-27 18:00–21:00', '2027-04-03 17:00–20:00'], $this->dates($event));

        $this->actingAs($this->staff)->get("/events/{$event->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('event.timezone', 'Europe/Copenhagen')
            ->where('event.startsAt', '2027-03-20 19:00')
            ->where('event.dates.2.date', '2027-04-03')
            ->where('event.dates.2.time', '19:00–22:00 CEST')
        );

        // Saving it unchanged keeps every date where it is.
        $ids = $event->occurrences()->pluck('id')->all();
        $this->actingAs($this->staff)->put("/events/{$event->id}", $this->payload($schedule))->assertSessionHasNoErrors();
        $this->assertSame($ids, $event->occurrences()->pluck('id')->all());
        $this->assertSame(['2027-03-20 18:00–21:00', '2027-03-27 18:00–21:00', '2027-04-03 17:00–20:00'], $this->dates($event));
    }

    public function test_dates_just_after_local_midnight_belong_to_the_local_day(): void
    {
        // 00:30 on Saturday in Copenhagen is still Friday in UTC.
        $this->actingAs($this->staff)->post('/events', $this->payload([
            'timezone' => 'Europe/Copenhagen',
            'starts_at' => '2026-11-14 00:30',
            'ends_at' => '2026-11-14 03:00',
            'repeats' => 'weekly',
            'repeat_ends' => 'count',
            'repeat_count' => 2,
        ]))->assertSessionHasNoErrors();

        $event = Event::sole();
        $this->assertSame(['2026-11-13 23:30–02:00', '2026-11-20 23:30–02:00'], $this->dates($event));
        $this->get("/events/{$event->id}")->assertInertia(fn (Assert $page) => $page->where('event.repeats', 'Every week on Saturday'));
    }

    public function test_repeating_with_no_end_tops_up_dates_at_the_same_local_time(): void
    {
        $this->actingAs($this->staff)->post('/events', $this->payload([
            'timezone' => 'Europe/Copenhagen',
            'starts_at' => '2026-11-10 19:00',
            'ends_at' => '2026-11-10 22:00',
            'repeats' => 'weekly',
            'repeat_ends' => 'never',
        ]))->assertSessionHasNoErrors();

        $event = Event::sole();
        $this->travelTo(Carbon::parse('2026-12-01 12:00', 'UTC'));
        $this->artisan('events:extend-repeats')->assertSuccessful();

        $localTimes = $event->occurrences()->get()->map(fn ($occurrence) => $occurrence->starts_at->setTimezone('Europe/Copenhagen')->format('H:i'))->unique()->values()->all();
        $this->assertSame(['19:00'], $localTimes);
        // The dates span the change to summer time on 28 March.
        $this->assertSame(['18:00', '17:00'], $event->occurrences()->get()->map(fn ($occurrence) => $occurrence->starts_at->utc()->format('H:i'))->unique()->values()->all());
    }

    public function test_the_time_zone_must_exist(): void
    {
        $this->actingAs($this->staff)
            ->post('/events', $this->payload(['timezone' => 'Mars/Olympus_Mons']))
            ->assertSessionHasErrors(['timezone' => 'Pick a time zone from the list.']);
    }

    public function test_events_with_an_upcoming_date_stay_listed(): void
    {
        $event = Event::factory()->create([
            'starts_at' => Carbon::parse('2026-10-20 18:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-10-20 21:00', 'UTC'),
        ]);
        $event->occurrences()->create(['starts_at' => Carbon::parse('2026-11-20 18:00', 'UTC'), 'ends_at' => Carbon::parse('2026-11-20 21:00', 'UTC')]);
        Event::factory()->past()->create();

        $this->get('/events')->assertInertia(fn (Assert $page) => $page
            ->has('events', 1)
            ->where('events.0.id', $event->id)
            ->where('canCreate', false)
        );
    }

    public function test_staff_can_delete_an_event_with_its_staffing(): void
    {
        $event = Event::factory()->create(['staffing_mode' => StaffingMode::Shifts]);
        $occurrence = $event->occurrences()->sole();
        $shift = $event->positions()->create(['callsign' => 'EKCH_TWR'])->shifts()->create(['starts_offset' => 0, 'ends_offset' => 60]);
        $booking = $shift->bookings()->create(['event_occurrence_id' => $occurrence->id, 'user_id' => User::factory()->create()->id]);

        $this->actingAs($this->staff)->delete("/events/{$event->id}")->assertRedirect('/events');

        $this->assertModelMissing($event);
        $this->assertModelMissing($occurrence);
        $this->assertModelMissing($booking);
    }

    public function test_uploaded_banners_are_stored_as_webp(): void
    {
        Storage::fake('public');

        $this->actingAs($this->staff)
            ->post('/events', $this->payload(['banner' => UploadedFile::fake()->image('banner.png', 3000, 1000)]))
            ->assertSessionHasNoErrors();

        $path = Event::sole()->banner_path;

        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame('image/webp', getimagesizefromstring(Storage::disk('public')->get($path))['mime']);
        // Scaled down to the widest banner the site shows.
        $this->assertSame(2400, getimagesizefromstring(Storage::disk('public')->get($path))[0]);

        $this->get('/events')->assertInertia(fn (Assert $page) => $page->where('events.0.banner', Storage::disk('public')->url($path)));
    }

    public function test_replacing_or_removing_a_banner_deletes_the_old_file(): void
    {
        Storage::fake('public');
        $this->actingAs($this->staff)->post('/events', $this->payload(['banner' => UploadedFile::fake()->image('first.jpg', 1200, 400)]));
        $event = Event::sole();
        $first = $event->banner_path;

        $this->actingAs($this->staff)->post("/events/{$event->id}", $this->payload([
            '_method' => 'put',
            'banner' => UploadedFile::fake()->image('second.gif', 900, 300),
        ]))->assertSessionHasNoErrors();

        $second = $event->fresh()->banner_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);

        // Saving without a new file keeps the banner.
        $this->actingAs($this->staff)->put("/events/{$event->id}", $this->payload())->assertSessionHasNoErrors();
        $this->assertSame($second, $event->fresh()->banner_path);

        $this->actingAs($this->staff)->put("/events/{$event->id}", $this->payload(['remove_banner' => true]))->assertSessionHasNoErrors();
        $this->assertNull($event->fresh()->banner_path);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_banners_must_be_images_and_are_deleted_with_the_event(): void
    {
        Storage::fake('public');

        $this->actingAs($this->staff)
            ->post('/events', $this->payload(['banner' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')]))
            ->assertSessionHasErrors('banner');

        $this->actingAs($this->staff)->post('/events', $this->payload(['banner' => UploadedFile::fake()->image('banner.png', 1200, 400)]));
        $event = Event::sole();

        $this->actingAs($this->staff)->delete("/events/{$event->id}");

        Storage::disk('public')->assertMissing($event->banner_path);
    }

    public function test_banners_too_large_to_decode_are_refused_before_decoding(): void
    {
        Storage::fake('public');

        // Only a PNG header claiming 20000×20000 pixels: decoding it for real would need gigabytes.
        $header = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.pack('NN', 20000, 20000)."\x08\x02\x00\x00\x00".pack('N', 0);
        $banner = UploadedFile::fake()->createWithContent('huge.png', $header);

        $this->actingAs($this->staff)
            ->post('/events', $this->payload(['banner' => $banner]))
            ->assertSessionHasErrors(['banner' => 'That image is too large. Use one under 25 megapixels, like 6000×4000.']);

        $this->assertDatabaseCount('events', 0);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_a_banner_is_not_left_behind_when_saving_the_event_fails(): void
    {
        Storage::fake('public');
        $this->withoutExceptionHandling();
        Event::creating(fn () => throw new RuntimeException('Database is down.'));

        try {
            $this->actingAs($this->staff)->post('/events', $this->payload(['banner' => UploadedFile::fake()->image('banner.png', 1200, 400)]));
            $this->fail('The save should have failed.');
        } catch (RuntimeException $e) {
            $this->assertSame('Database is down.', $e->getMessage());
        }

        $this->assertEmpty(Storage::disk('public')->allFiles());
    }
}
