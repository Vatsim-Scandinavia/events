<?php

namespace Tests\Feature;

use App\Integrations\ConnectionFailed;
use App\Integrations\ControlCenter\PushBookings;
use App\Integrations\ControlCenter\PushedBooking;
use App\Integrations\RunSync;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventPosition;
use App\Models\Fir;
use App\Models\IntegrationSetting;
use App\Models\PositionShift;
use App\Models\User;
use App\Staffing\EventSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ControlCenterBookingPushTest extends TestCase
{
    use RefreshDatabase;

    /**
     * What Control Center was asked to do, as "METHOD path" with the body sent.
     *
     * @var list<array{0: string, 1: array<string, mixed>}>
     */
    private array $calls = [];

    /** How Control Center answers bookings; null for success. */
    private ?\Closure $answer = null;

    private int $nextId = 500;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-01 12:00', 'UTC'));
        config(['integrations.settings.control-center' => []]);
        Fir::factory()->create(['icao' => 'EKDK']);
        $this->switchOn();

        Http::fake(function (Request $request) {
            $path = str($request->url())->after('/api/v1/')->before('?')->toString();
            $this->calls[] = [strtoupper($request->method()).' '.$path, $request->data()];

            if ($path === 'users') {
                return Http::response(['data' => []]);
            }

            if ($this->answer && ($answer = ($this->answer)($request, $path))) {
                return $answer;
            }

            return $request->method() === 'POST'
                ? Http::response(['success' => 'Booking created', 'booking' => ['id' => $this->nextId++]])
                : Http::response(['message' => 'ok']);
        });
    }

    public function test_making_and_cancelling_a_shift_booking_creates_and_deletes_it_upstream(): void
    {
        [$event, $shift] = $this->shiftEvent();
        $occurrence = $event->occurrences()->sole();
        $controller = User::factory()->controller()->create(['vatsim_cid' => 1234567]);

        $this->actingAs($controller)->post("/occurrences/{$occurrence->id}/shifts/{$shift->id}/booking")->assertSessionHasNoErrors();

        $this->assertSame([['POST bookings/create', [
            'cid' => 1234567,
            'date' => '10/11/2026',
            'start_at' => '16:00',
            'end_at' => '18:00',
            'position' => 'EKCH_TWR',
            'tag' => 3,
            'source' => 'EVENTHUB',
        ]]], $this->calls);
        $this->assertSame(500, PushedBooking::query()->sole()->upstream_id);

        $booking = $occurrence->bookings()->sole();
        $this->actingAs($controller)->delete("/bookings/{$booking->id}")->assertSessionHasNoErrors();

        $this->assertSame('DELETE bookings/500', $this->calls[1][0]);
        $this->assertSame(0, PushedBooking::query()->count());
    }

    public function test_a_published_roster_is_pushed_and_follows_its_changes_until_unpublished(): void
    {
        $event = Event::factory()->withOpenInterest()->create([
            'starts_at' => Carbon::parse('2026-11-10 16:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-10 20:00', 'UTC'),
        ]);
        $occurrence = $event->occurrences()->sole();
        $tower = $event->positions()->create(['callsign' => 'EKCH_TWR']);
        $kim = User::factory()->controller()->create(['vatsim_cid' => 1000001]);
        $sam = User::factory()->controller()->create(['vatsim_cid' => 1000002]);
        $staff = User::factory()->staff()->create();
        $plan = fn (array $roster, bool $publish = false) => $this->actingAs($staff)->put("/events/{$event->id}/staffing/plan", [
            'occurrence_id' => $occurrence->id,
            'positions' => [['key' => $tower->id, 'callsign' => 'EKCH_TWR']],
            'roster' => $roster,
            'publish' => $publish,
        ])->assertSessionHasNoErrors();

        // Unpublished, nothing goes out.
        $plan([['position' => $tower->id, 'user_id' => $kim->id, 'start' => 0, 'end' => 120]]);
        $this->assertSame([], $this->calls);

        $plan([['id' => $occurrence->rosterEntries()->sole()->id, 'position' => $tower->id, 'user_id' => $kim->id, 'start' => 0, 'end' => 120]], publish: true);
        $this->assertSame(['POST bookings/create'], $this->methods());

        // Kim's entry moves; Sam is added.
        $kimsEntry = $occurrence->rosterEntries()->sole()->id;
        $plan([
            ['id' => $kimsEntry, 'position' => $tower->id, 'user_id' => $kim->id, 'start' => 0, 'end' => 90],
            ['position' => $tower->id, 'user_id' => $sam->id, 'start' => 90, 'end' => 240],
        ]);
        $this->assertSame(['POST bookings/create', 'PATCH bookings/500', 'POST bookings/create'], $this->methods());
        $this->assertSame('17:30', $this->calls[1][1]['end_at']);

        // Kim's slot is handed to Sam's colleague: Control Center can't reassign, so it's replaced.
        $samsEntry = $occurrence->rosterEntries()->where('user_id', $sam->id)->sole()->id;
        $plan([
            ['id' => $kimsEntry, 'position' => $tower->id, 'user_id' => User::factory()->controller()->create(['vatsim_cid' => 1000003])->id, 'start' => 0, 'end' => 90],
            ['id' => $samsEntry, 'position' => $tower->id, 'user_id' => $sam->id, 'start' => 90, 'end' => 240],
        ]);
        $this->assertSame(['DELETE bookings/500', 'POST bookings/create'], array_slice($this->methods(), 3));

        $this->actingAs($staff)->delete("/occurrences/{$occurrence->id}/roster/publish")->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['DELETE bookings/501', 'DELETE bookings/502'], array_slice($this->methods(), 5));
        $this->assertSame(0, PushedBooking::query()->count());
    }

    public function test_moving_the_date_or_renaming_the_position_moves_bookings_and_unbooking_or_cancelling_deletes_them(): void
    {
        [$event, $shift] = $this->shiftEvent();
        $occurrence = $event->occurrences()->sole();
        $this->book($occurrence, $shift);

        app(EventSchedule::class)->save($event, collect([[
            'id' => $occurrence->id,
            'starts_at' => Carbon::parse('2026-11-11 16:00', 'UTC')->toImmutable(),
            'ends_at' => Carbon::parse('2026-11-11 20:00', 'UTC')->toImmutable(),
        ]]));
        $this->assertSame(['PATCH bookings/500', '11/11/2026'], [$this->calls[1][0], $this->calls[1][1]['date']]);

        $staff = User::factory()->staff()->create();
        $planShift = fn (string $callsign, int $start, int $end) => $this->actingAs($staff)->put("/events/{$event->id}/staffing/plan", [
            'occurrence_id' => $occurrence->id,
            'positions' => [['key' => $shift->event_position_id, 'callsign' => $callsign]],
            'shifts' => [['id' => $shift->id, 'position' => $shift->event_position_id, 'start' => $start, 'end' => $end]],
        ])->assertSessionHasNoErrors();

        // Renaming the position keeps the booking, so it's moved upstream.
        $planShift('EKCH_N_TWR', 0, 120);
        $this->assertSame(['PATCH bookings/500', 'EKCH_N_TWR'], [$this->calls[2][0], $this->calls[2][1]['position']]);

        // Moving a booked shift releases it, so the booking goes upstream too.
        $planShift('EKCH_N_TWR', 60, 180);
        $this->assertSame('DELETE bookings/500', $this->calls[3][0]);

        $this->book($occurrence, $shift->fresh(), cid: 7654321);
        $this->assertSame('POST bookings/create', $this->calls[4][0]);

        $this->actingAs($staff)->post("/occurrences/{$occurrence->id}/cancel")->assertSessionHasNoErrors();
        $this->assertSame('DELETE bookings/501', $this->calls[5][0]);
    }

    public function test_deleting_the_event_deletes_its_bookings_upstream(): void
    {
        [$event, $shift] = $this->shiftEvent();
        $this->book($event->occurrences()->sole(), $shift);

        $this->actingAs(User::factory()->admin()->create())->delete("/events/{$event->id}")->assertRedirect('/events');

        $this->assertSame('DELETE bookings/500', $this->calls[1][0]);
        $this->assertSame(0, PushedBooking::query()->count());
    }

    public function test_a_refused_booking_is_marked_not_sent_with_the_reason_and_not_retried(): void
    {
        $this->answer = fn (Request $request, string $path) => $path === 'bookings/create'
            ? Http::response(['message' => 'Booking overlaps with existing booking'], 400)
            : null;
        [$event, $shift] = $this->shiftEvent();
        $occurrence = $event->occurrences()->sole();
        $this->book($occurrence, $shift);

        $record = PushedBooking::query()->sole();
        $this->assertSame([PushedBooking::NOT_SENT, 'Booking overlaps with existing booking'], [$record->status, $record->reason]);

        // Nothing changed, so the next push doesn't try again.
        PushBookings::dispatchSync($occurrence->id);
        $this->assertCount(1, $this->calls);

        $this->actingAs(User::factory()->admin()->create())->get('/admin/integrations/control-center')->assertInertia(fn (Assert $page) => $page
            ->where('integration.features.2.key', 'bookings')
            ->where('integration.features.2.needsAttention', ['EKCH_TWR, Tue 10 Nov 16:00–18:00z (CID 1234567): not sent. Booking overlaps with existing booking'])
        );
    }

    public function test_an_unknown_controller_is_explained(): void
    {
        $this->answer = fn (Request $request, string $path) => $path === 'bookings/create'
            ? Http::response(['message' => 'No query results for model [App\\Models\\User] 1234567'], 404)
            : null;
        [$event, $shift] = $this->shiftEvent();
        $this->book($event->occurrences()->sole(), $shift);

        $this->assertSame('Control Center doesn’t know controller 1234567.', PushedBooking::query()->sole()->reason);
    }

    public function test_a_vatsim_failure_behind_control_center_is_tried_again_at_the_next_sweep(): void
    {
        $failing = true;
        $this->answer = function (Request $request, string $path) use (&$failing) {
            return $failing && $path === 'bookings/create' ? Http::response(['message' => 'VATSIM API error: timeout'], 400) : null;
        };
        [$event, $shift] = $this->shiftEvent();
        $this->book($event->occurrences()->sole(), $shift);
        $this->assertTrue(PushedBooking::query()->sole()->retryable);

        $failing = false;
        $this->sweep();

        $this->assertSame(PushedBooking::SENT, PushedBooking::query()->sole()->status);
    }

    public function test_control_center_being_down_never_stops_a_booking_and_the_push_is_retried(): void
    {
        $this->answer = fn () => Http::response(['message' => 'Server Error'], 503);
        [$event, $shift] = $this->shiftEvent();
        $occurrence = $event->occurrences()->sole();

        $this->actingAs(User::factory()->controller()->create(['vatsim_cid' => 1234567]))
            ->post("/occurrences/{$occurrence->id}/shifts/{$shift->id}/booking")
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $occurrence->bookings()->count());
        $this->assertSame(0, PushedBooking::query()->count());

        // The queued job fails, so the queue tries it again later.
        $this->expectException(ConnectionFailed::class);
        app()->call([new PushBookings($occurrence->id), 'handle']);
    }

    public function test_switching_it_on_sends_upcoming_bookings_and_switching_off_leaves_them(): void
    {
        $this->switchOn(features: []);
        [$event, $shift] = $this->shiftEvent();
        $this->book($event->occurrences()->sole(), $shift);
        $past = Event::factory()->withShifts()->create([
            'starts_at' => Carbon::parse('2026-10-20 16:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-10-20 20:00', 'UTC'),
        ]);
        $pastShift = $past->positions()->create(['callsign' => 'EKCH_APP'])->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);
        $past->occurrences()->sole()->bookings()->create(['position_shift_id' => $pastShift->id, 'user_id' => User::factory()->controller()->create()->id]);
        $this->assertSame([], $this->calls);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/admin/integrations/control-center', $this->settings(['bookings']))->assertSessionHasNoErrors();

        $this->assertSame(['GET users', 'POST bookings/create', 'POST bookings/create'], $this->methods());
        $this->assertSame('EKCH_TWR', $this->calls[2][1]['position']);

        $this->actingAs($admin)->put('/admin/integrations/control-center', $this->settings([]))->assertSessionHasNoErrors();
        $this->actingAs($admin)->delete("/bookings/{$event->occurrences()->sole()->bookings()->sole()->id}")->assertSessionHasNoErrors();

        $this->assertSame(['GET users'], array_slice($this->methods(), 3));
    }

    public function test_switching_it_on_needs_a_key_that_can_edit(): void
    {
        $this->switchOn(features: []);
        $this->answer = fn (Request $request, string $path) => $path === 'bookings/create' ? Http::response(['message' => 'Unauthorized'], 401) : null;

        $this->actingAs(User::factory()->admin()->create())
            ->put('/admin/integrations/control-center', $this->settings(['bookings']))
            ->assertSessionHasErrors(['enabled' => 'Couldn’t connect, so it stays off: The API key can only read. Booking push needs a key that can edit.']);
        // The check books nothing.
        $this->assertSame([], $this->calls[1][1]);
    }

    /**
     * An event on 10 November, 16:00–20:00z, with one two-hour shift on EKCH_TWR.
     *
     * @return array{0: Event, 1: PositionShift}
     */
    private function shiftEvent(): array
    {
        $event = Event::factory()->withShifts()->create([
            'starts_at' => Carbon::parse('2026-11-10 16:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-10 20:00', 'UTC'),
        ]);

        /** @var EventPosition $position */
        $position = $event->positions()->create(['callsign' => 'EKCH_TWR']);

        return [$event, $position->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120])];
    }

    private function book(EventOccurrence $occurrence, PositionShift $shift, int $cid = 1234567): void
    {
        $this->actingAs(User::factory()->controller()->create(['vatsim_cid' => $cid]))
            ->post("/occurrences/{$occurrence->id}/shifts/{$shift->id}/booking")
            ->assertSessionHasNoErrors();
    }

    private function sweep(): void
    {
        app()->call([new RunSync('control-center', 'bookings'), 'handle']);
    }

    /**
     * @return list<string>
     */
    private function methods(): array
    {
        return array_column($this->calls, 0);
    }

    /**
     * @param  list<string>  $features
     */
    private function switchOn(array $features = ['bookings']): void
    {
        IntegrationSetting::query()->updateOrCreate(['key' => 'control-center'], [
            'enabled' => true,
            'features' => $features,
            'settings' => ['url' => 'https://cc.example', 'token' => 'cc-key', 'areas' => 'denmark = EKDK'],
        ]);
    }

    /**
     * @param  list<string>  $features
     * @return array<string, mixed>
     */
    private function settings(array $features): array
    {
        return [
            'enabled' => true,
            'features' => $features,
            'removal_limit' => 25,
            'settings' => ['url' => 'https://cc.example', 'token' => '', 'areas' => 'denmark = EKDK'],
        ];
    }
}
