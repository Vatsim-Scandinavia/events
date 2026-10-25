<?php

namespace Tests\Feature;

use App\Enums\StaffingMode;
use App\Integrations\ControlCenter\Member;
use App\Integrations\ControlCenter\PushedBooking;
use App\Models\Event;
use App\Models\Fir;
use App\Models\IntegrationSetting;
use App\Models\PositionShift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Notes from Control Center next to staffing: endorsement warnings from Roster sync, and
 * bookings Booking push couldn't send. Shown, never enforced.
 */
class StaffingNotesTest extends TestCase
{
    use RefreshDatabase;

    private Fir $denmark;

    private Fir $norway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-01 12:00', 'UTC'));
        config(['integrations.settings.control-center' => []]);
        $this->denmark = Fir::factory()->create(['icao' => 'EKDK']);
        $this->norway = Fir::factory()->create(['icao' => 'ENOR']);
    }

    public function test_without_a_feature_with_notes_nothing_is_noted(): void
    {
        [$event, $shift] = $this->shiftEvent('EKCH_TWR');
        $controller = $this->controller(1000001, 'S1', active: ['denmark']);
        $this->switchOn([]);

        $this->book($controller, $event, $shift);

        $this->actingAs($controller)->get("/events/{$event->id}")->assertInertia(fn (Assert $page) => $page
            ->where('staffing.positions.0.note', null)
            ->where('staffing.positions.0.shifts.0.booking.notes', [])
        );
    }

    public function test_a_controller_rated_below_the_position_is_warned_but_still_booked(): void
    {
        [$event, $shift] = $this->shiftEvent('EKCH_TWR');
        $controller = $this->controller(1000001, 'S1', active: ['denmark']);
        $this->switchOn(['roster']);
        $note = 'Rated S1 in Control Center; EKCH_TWR needs S2.';

        $this->actingAs($controller)->get("/events/{$event->id}")->assertInertia(fn (Assert $page) => $page->where('staffing.positions.0.note', $note));

        $this->book($controller, $event, $shift);
        $this->assertSame(1, $event->occurrences()->sole()->bookings()->count());

        $this->actingAs($controller)->get("/events/{$event->id}")->assertInertia(fn (Assert $page) => $page->where('staffing.positions.0.shifts.0.booking.notes', [$note]));
        $this->actingAs(User::factory()->staff()->create())->get("/events/{$event->id}/staffing")->assertInertia(fn (Assert $page) => $page
            ->where('positions.0.shifts.0.booking.notes', [$note])
        );

        // Other controllers and guests don't see it.
        $this->actingAs(User::factory()->controller()->create())->get("/events/{$event->id}")->assertInertia(fn (Assert $page) => $page
            ->where('staffing.positions.0.shifts.0.booking.notes', [])
        );
        auth()->logout();
        $this->get("/events/{$event->id}")->assertInertia(fn (Assert $page) => $page
            ->where('staffing.positions.0.note', null)
            ->where('staffing.positions.0.shifts.0.booking.notes', [])
        );
    }

    public function test_a_solo_endorsement_covers_its_position(): void
    {
        [$event, $shift] = $this->shiftEvent('EKCH_APP');
        $student = $this->controller(1000001, 'S2', active: ['denmark'], endorsements: ['solo' => [['positions' => ['EKCH_APP']]]]);
        $this->switchOn(['roster']);

        $this->book($student, $event, $shift);

        $this->actingAs($student)->get("/events/{$event->id}")->assertInertia(fn (Assert $page) => $page
            ->where('staffing.positions.0.note', null)
            ->where('staffing.positions.0.shifts.0.booking.notes', [])
        );
    }

    public function test_a_visiting_controller_is_covered_in_their_endorsements_firs_only(): void
    {
        [$danish] = $this->shiftEvent('EKCH_APP', $this->denmark);
        [$norwegian] = $this->shiftEvent('ENGM_APP', $this->norway);
        $visitor = $this->controller(1000001, 'C1', endorsements: ['visiting' => [['rating' => 'S3', 'areas' => ['Norway']]]]);
        $this->switchOn(['roster']);

        $this->actingAs($visitor)->get("/events/{$norwegian->id}")->assertInertia(fn (Assert $page) => $page->where('staffing.positions.0.note', null));
        $this->actingAs($visitor)->get("/events/{$danish->id}")->assertInertia(fn (Assert $page) => $page
            ->where('staffing.positions.0.note', 'Not active in EKDK in Control Center, and no visiting endorsement for it.')
        );
    }

    public function test_positions_with_an_unknown_suffix_are_never_warned_about(): void
    {
        [$event] = $this->shiftEvent('EKCH_X_INFO');
        $controller = $this->controller(1000001, 'OBS');
        $this->switchOn(['roster']);

        $this->actingAs($controller)->get("/events/{$event->id}")->assertInertia(fn (Assert $page) => $page->where('staffing.positions.0.note', null));
    }

    public function test_rostering_an_uncovered_controller_works_and_warns_on_the_roster(): void
    {
        $event = Event::factory()->withOpenInterest()->create([
            'fir_id' => $this->denmark->id,
            'starts_at' => Carbon::parse('2026-11-10 16:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-10 20:00', 'UTC'),
        ]);
        $centre = $event->positions()->create(['callsign' => 'EKDK_CTR']);
        $controller = $this->controller(1000001, 'S3', active: ['denmark']);
        $staff = User::factory()->staff()->create();
        $this->switchOn(['roster']);

        $this->actingAs($staff)->put("/events/{$event->id}/staffing/plan", [
            'occurrence_id' => $event->occurrences()->sole()->id,
            'positions' => [['key' => $centre->id, 'callsign' => 'EKDK_CTR']],
            'roster' => [['position' => $centre->id, 'user_id' => $controller->id, 'start' => 0, 'end' => 120]],
        ])->assertSessionHasNoErrors();

        $this->actingAs($staff)->get("/events/{$event->id}/staffing")->assertInertia(fn (Assert $page) => $page
            ->where('roster.0.notes', ['Rated S3 in Control Center; EKDK_CTR needs C1.'])
        );
    }

    public function test_a_booking_control_center_turned_down_is_noted_for_staff(): void
    {
        [$event, $shift] = $this->shiftEvent('EKCH_TWR');
        $controller = User::factory()->controller()->create(['vatsim_cid' => 1000001]);
        $booking = $event->occurrences()->sole()->bookings()->create(['position_shift_id' => $shift->id, 'user_id' => $controller->id]);
        PushedBooking::query()->create([
            'event_occurrence_id' => $booking->event_occurrence_id,
            'source' => 'shift_booking',
            'source_id' => $booking->id,
            'cid' => 1000001,
            'callsign' => 'EKCH_TWR',
            'starts_at' => now()->addDays(9),
            'ends_at' => now()->addDays(9)->addHours(2),
            'status' => PushedBooking::NOT_SENT,
            'reason' => 'Booking overlaps with existing booking',
        ]);
        $this->switchOn(['bookings']);

        $this->actingAs(User::factory()->staff()->create())->get("/events/{$event->id}/staffing")->assertInertia(fn (Assert $page) => $page
            ->where('positions.0.shifts.0.booking.notes', ['Not sent to Control Center: Booking overlaps with existing booking'])
        );
    }

    /**
     * An event on 10 November with one shift on the position, in Denmark unless said otherwise.
     *
     * @return array{0: Event, 1: PositionShift}
     */
    private function shiftEvent(string $callsign, ?Fir $fir = null): array
    {
        $event = Event::factory()->create([
            'fir_id' => ($fir ?? $this->denmark)->id,
            'staffing_mode' => StaffingMode::Shifts,
            'starts_at' => Carbon::parse('2026-11-10 16:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-10 20:00', 'UTC'),
        ]);

        return [$event, $event->positions()->create(['callsign' => $callsign])->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120])];
    }

    /**
     * A controller with the role across the hub, as Control Center lists them.
     *
     * @param  list<string>  $active
     * @param  array<string, mixed>  $endorsements
     */
    private function controller(int $cid, string $rating, array $active = [], array $endorsements = []): User
    {
        Member::query()->create(['cid' => $cid, 'rating' => $rating, 'active_areas' => $active, 'endorsements' => $endorsements, 'synced_at' => now()]);

        return User::factory()->controller()->create(['vatsim_cid' => $cid]);
    }

    private function book(User $controller, Event $event, PositionShift $shift): void
    {
        $this->actingAs($controller)
            ->post("/occurrences/{$event->occurrences()->sole()->id}/shifts/{$shift->id}/booking")
            ->assertSessionHasNoErrors();
    }

    /**
     * @param  list<string>  $features
     */
    private function switchOn(array $features): void
    {
        IntegrationSetting::query()->updateOrCreate(['key' => 'control-center'], [
            'enabled' => true,
            'features' => $features,
            'settings' => ['url' => 'https://cc.example', 'token' => 'cc-key', 'areas' => "denmark = EKDK\nnorway = ENOR"],
        ]);
    }
}
