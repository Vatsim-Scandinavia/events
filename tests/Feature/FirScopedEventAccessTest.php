<?php

namespace Tests\Feature;

use App\Auth\Roles;
use App\Models\Event;
use App\Models\Fir;
use App\Models\ShiftBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FirScopedEventAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-01 12:00', 'UTC'));
    }

    /**
     * Every staff endpoint for an event, with {event} and {occurrence} filled in by the test.
     *
     * @return array<string, array{string, string}>
     */
    public static function staffEndpoints(): array
    {
        return [
            'edit page' => ['get', '/events/{event}/edit'],
            'save' => ['put', '/events/{event}'],
            'preview the schedule' => ['post', '/events/{event}/schedule'],
            'delete' => ['delete', '/events/{event}'],
            'cancel the event' => ['post', '/events/{event}/cancel'],
            'restore the event' => ['delete', '/events/{event}/cancel'],
            'cancel a date' => ['post', '/occurrences/{occurrence}/cancel'],
            'restore a date' => ['delete', '/occurrences/{occurrence}/cancel'],
            'staffing page' => ['get', '/events/{event}/staffing'],
            'staffing settings' => ['put', '/events/{event}/staffing'],
            'staffing plan' => ['put', '/events/{event}/staffing/plan'],
            'publish a roster' => ['post', '/occurrences/{occurrence}/roster/publish'],
            'hide a roster' => ['delete', '/occurrences/{occurrence}/roster/publish'],
        ];
    }

    #[DataProvider('staffEndpoints')]
    public function test_staff_of_one_fir_cannot_touch_another_firs_event(string $method, string $uri): void
    {
        $staff = User::factory()->staff(Fir::factory()->create(['icao' => 'EKDK']))->create();
        $event = Event::factory()->create(['fir_id' => Fir::factory()->create(['icao' => 'ESAA'])->id]);
        $occurrence = $event->occurrences()->sole();

        $this->actingAs($staff)
            ->{$method}(str_replace(['{event}', '{occurrence}'], [$event->id, $occurrence->id], $uri))
            ->assertForbidden();

        $this->assertModelExists($event);
        $this->assertNull($event->fresh()->cancelled_at);
    }

    public function test_staff_only_see_edit_controls_on_their_own_firs_events(): void
    {
        $ekdk = Fir::factory()->create(['icao' => 'EKDK']);
        $staff = User::factory()->staff($ekdk)->create();
        $own = Event::factory()->create(['fir_id' => $ekdk->id]);
        $other = Event::factory()->create(['fir_id' => Fir::factory()->create()->id]);

        $this->actingAs($staff)->get("/events/{$own->id}")->assertInertia(fn (Assert $page) => $page
            ->where('canEdit', true)
            ->where('staffing.canManage', true)
        );
        $this->actingAs($staff)->get("/events/{$other->id}")->assertInertia(fn (Assert $page) => $page
            ->where('canEdit', false)
            ->where('staffing.canManage', false)
        );
        $this->actingAs($staff)->get("/events/{$own->id}/edit")->assertOk();
    }

    public function test_staff_can_only_add_events_to_firs_they_manage(): void
    {
        $ekdk = Fir::factory()->create(['icao' => 'EKDK', 'name' => 'Copenhagen']);
        $esaa = Fir::factory()->create(['icao' => 'ESAA']);
        $staff = User::factory()->staff($ekdk)->create();

        $this->actingAs($staff)->get('/events/create')->assertInertia(fn (Assert $page) => $page
            ->where('firs', [['id' => $ekdk->id, 'icao' => 'EKDK', 'name' => 'Copenhagen']])
        );

        $this->actingAs($staff)
            ->post('/events', $this->payload($esaa))
            ->assertSessionHasErrors(['fir_id' => 'You can only add events to FIRs you manage.']);
        $this->assertDatabaseCount('events', 0);

        $this->actingAs($staff)->post('/events', $this->payload($ekdk))->assertSessionHasNoErrors();
        $this->assertTrue(Event::query()->sole()->fir->is($ekdk));
    }

    public function test_moving_an_event_needs_both_firs(): void
    {
        $ekdk = Fir::factory()->create();
        $esaa = Fir::factory()->create();
        $event = Event::factory()->create(['fir_id' => $ekdk->id]);

        $this->actingAs(User::factory()->staff($ekdk)->create())
            ->put("/events/{$event->id}", $this->payload($esaa))
            ->assertSessionHasErrors(['fir_id' => 'You can only add events to FIRs you manage.']);
        $this->assertTrue($event->fresh()->fir->is($ekdk));

        $this->actingAs(User::factory()->staff()->create())
            ->put("/events/{$event->id}", $this->payload($esaa))
            ->assertSessionHasNoErrors();
        $this->assertTrue($event->fresh()->fir->is($esaa));
    }

    public function test_staff_can_only_remove_bookings_on_their_own_firs_events(): void
    {
        $ekdk = Fir::factory()->create();
        $event = Event::factory()->withShifts()->create(['fir_id' => Fir::factory()->create()->id]);
        $shift = $event->positions()->create(['callsign' => 'ESSA_TWR'])->shifts()->create(['starts_offset' => 0, 'ends_offset' => 60]);
        $booking = ShiftBooking::create([
            'position_shift_id' => $shift->id,
            'event_occurrence_id' => $event->occurrences()->sole()->id,
            'user_id' => User::factory()->controller()->create()->id,
        ]);

        $this->actingAs(User::factory()->staff($ekdk)->create())->delete("/bookings/{$booking->id}")->assertForbidden();
        $this->assertModelExists($booking);

        $this->actingAs(User::factory()->staff()->create())->delete("/bookings/{$booking->id}");
        $this->assertModelMissing($booking);
    }

    public function test_controllers_of_one_fir_can_only_book_that_firs_events(): void
    {
        $ekdk = Fir::factory()->create();
        $visitor = User::factory()->rated()->withRole(Roles::CONTROLLER, $ekdk)->create();

        foreach ([[$ekdk, 302], [Fir::factory()->create(), 403]] as [$fir, $status]) {
            $event = Event::factory()->withShifts()->create(['fir_id' => $fir->id]);
            $shift = $event->positions()->create(['callsign' => 'EKCH_TWR'])->shifts()->create(['starts_offset' => 0, 'ends_offset' => 60]);

            $this->actingAs($visitor)
                ->post("/occurrences/{$event->occurrences()->sole()->id}/shifts/{$shift->id}/booking")
                ->assertStatus($status);
        }
    }

    public function test_the_roster_picker_only_lists_controllers_who_may_staff_the_event(): void
    {
        $ekdk = Fir::factory()->create();
        $event = Event::factory()->withOpenInterest()->create(['fir_id' => $ekdk->id]);
        User::factory()->controller()->create(['name' => 'Hub Controller']);
        User::factory()->rated()->withRole(Roles::CONTROLLER, $ekdk)->create(['name' => 'Copenhagen Controller']);
        User::factory()->rated()->withRole(Roles::CONTROLLER, Fir::factory()->create())->create(['name' => 'Stockholm Controller']);

        $this->actingAs(User::factory()->staff()->create())
            ->get("/events/{$event->id}/staffing")
            ->assertInertia(fn (Assert $page) => $page
                ->where('controllers', fn ($controllers) => collect($controllers)->pluck('name')->all() === ['Copenhagen Controller', 'Hub Controller']));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Fir $fir): array
    {
        return [
            'fir_id' => $fir->id,
            'name' => 'Copenhagen Fly-in',
            'airports' => ['EKCH'],
            'routes' => [],
            'starts_at' => '2026-11-10 18:00',
            'ends_at' => '2026-11-10 21:00',
            'repeats' => 'none',
        ];
    }
}
