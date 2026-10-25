<?php

namespace Tests\Feature\Staffing;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\PositionShift;
use App\Models\ShiftBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShiftBookingTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private EventOccurrence $occurrence;

    private PositionShift $towerEarly;

    private PositionShift $towerLate;

    private PositionShift $approachEarly;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-01 12:00', 'UTC'));

        // 16:00–20:00z on 10 November.
        $this->event = Event::factory()->withShifts()->create([
            'starts_at' => Carbon::parse('2026-11-10 16:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-10 20:00', 'UTC'),
        ]);
        $this->occurrence = $this->event->occurrences()->sole();

        $tower = $this->event->positions()->create(['callsign' => 'EKCH_TWR', 'frequency' => '118.105']);
        $approach = $this->event->positions()->create(['callsign' => 'EKCH_APP', 'frequency' => '119.805']);

        $this->towerEarly = $tower->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);
        $this->towerLate = $tower->shifts()->create(['starts_offset' => 120, 'ends_offset' => 240]);
        $this->approachEarly = $approach->shifts()->create(['starts_offset' => 60, 'ends_offset' => 180]);
    }

    private function book(User $user, PositionShift $shift, ?EventOccurrence $occurrence = null)
    {
        $occurrence ??= $this->occurrence;

        return $this->actingAs($user)->post("/occurrences/{$occurrence->id}/shifts/{$shift->id}/booking");
    }

    public function test_a_controller_can_book_a_free_shift(): void
    {
        $controller = User::factory()->controller()->create();

        $this->book($controller, $this->towerEarly)->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('shift_bookings', [
            'position_shift_id' => $this->towerEarly->id,
            'event_occurrence_id' => $this->occurrence->id,
            'user_id' => $controller->id,
        ]);
    }

    public function test_a_shift_someone_else_booked_cannot_be_booked(): void
    {
        $this->book(User::factory()->controller()->create(), $this->towerEarly)->assertSessionHasNoErrors();

        $this->book(User::factory()->controller()->create(), $this->towerEarly)
            ->assertSessionHasErrors(['booking' => 'Someone else has already booked this shift.']);
        $this->assertSame(1, ShiftBooking::query()->count());
    }

    public function test_losing_a_race_for_the_same_shift_shows_it_as_taken(): void
    {
        $rival = User::factory()->controller()->create();

        // The rival's booking lands after the check but before this insert.
        ShiftBooking::creating(fn () => DB::table('shift_bookings')->insertOrIgnore([
            'position_shift_id' => $this->towerEarly->id,
            'event_occurrence_id' => $this->occurrence->id,
            'user_id' => $rival->id,
        ]));

        $loser = User::factory()->controller()->create();

        $this->book($loser, $this->towerEarly)
            ->assertSessionHasErrors(['booking' => 'Someone else has already booked this shift.']);
        $this->assertDatabaseMissing('shift_bookings', ['user_id' => $loser->id]);
    }

    public function test_back_to_back_shifts_can_be_booked_by_the_same_controller(): void
    {
        $controller = User::factory()->controller()->create();

        $this->book($controller, $this->towerEarly)->assertSessionHasNoErrors();
        $this->book($controller, $this->towerLate)->assertSessionHasNoErrors();

        $this->assertSame(2, $controller->shiftBookings()->count());
    }

    public function test_a_controller_cannot_book_overlapping_shifts(): void
    {
        $controller = User::factory()->controller()->create();

        $this->book($controller, $this->towerEarly)->assertSessionHasNoErrors();
        $this->book($controller, $this->approachEarly)->assertSessionHasErrors('booking');

        $this->assertSame(1, $controller->shiftBookings()->count());
    }

    public function test_the_overlap_message_uses_the_controllers_time_zone(): void
    {
        $controller = User::factory()->controller()->withTimezone('Europe/Copenhagen')->create();

        $this->book($controller, $this->towerEarly);
        $this->book($controller, $this->approachEarly)
            ->assertSessionHasErrors(['booking' => 'This shift overlaps your booking on EKCH_TWR (17:00–19:00 CET).']);
    }

    public function test_shift_times_are_shown_in_the_viewers_time_zone(): void
    {
        $this->actingAs(User::factory()->withTimezone('Europe/Copenhagen')->create())
            ->get("/events/{$this->event->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('staffing.occurrence.time', '17:00–21:00 CET')
                ->where('staffing.positions.0.shifts.0.time', '17:00–19:00 CET')
                // Offsets stay in minutes from the start for anything that edits them.
                ->where('staffing.positions.0.shifts.0.startOffset', 0)
            );
    }

    public function test_overlaps_are_checked_against_bookings_on_other_events(): void
    {
        $controller = User::factory()->controller()->create();

        $other = Event::factory()->withShifts()->create([
            'starts_at' => Carbon::parse('2026-11-10 17:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-10 19:00', 'UTC'),
        ]);
        $otherShift = $other->positions()->create(['callsign' => 'ESSA_TWR'])->shifts()->create(['starts_offset' => 0, 'ends_offset' => 60]);

        $this->book($controller, $this->towerEarly)->assertSessionHasNoErrors();
        $this->book($controller, $otherShift, $other->occurrences()->sole())->assertSessionHasErrors('booking');
    }

    public function test_a_shift_can_only_be_booked_once_per_occurrence(): void
    {
        $this->book(User::factory()->controller()->create(), $this->towerEarly)->assertSessionHasNoErrors();
        $this->book(User::factory()->controller()->create(), $this->towerEarly)->assertSessionHasErrors('booking');

        $this->assertSame(1, ShiftBooking::count());
    }

    public function test_only_the_next_occurrence_can_be_booked(): void
    {
        $nextWeek = $this->event->occurrences()->create([
            'starts_at' => Carbon::parse('2026-11-17 16:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-17 20:00', 'UTC'),
        ]);

        $this->book(User::factory()->controller()->create(), $this->towerEarly)->assertSessionHasNoErrors();
        $this->book(User::factory()->controller()->create(), $this->towerEarly, $nextWeek)->assertSessionHasErrors('booking');

        // Once the first date is over, the one after it opens for booking.
        $this->travelTo(Carbon::parse('2026-11-10 20:00', 'UTC'));
        $this->book(User::factory()->controller()->create(), $this->towerEarly, $nextWeek)->assertSessionHasNoErrors();

        $this->assertSame(2, ShiftBooking::count());
    }

    public function test_booking_closes_when_the_event_starts(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 16:00', 'UTC'));

        $this->book(User::factory()->controller()->create(), $this->towerLate)->assertSessionHasErrors('booking');
    }

    public function test_observers_and_guests_cannot_book(): void
    {
        $this->book(User::factory()->create(), $this->towerEarly)->assertForbidden();

        $this->actingAsGuest();
        $this->post("/occurrences/{$this->occurrence->id}/shifts/{$this->towerEarly->id}/booking")->assertRedirect('/auth/vatsim');

        $this->assertSame(0, ShiftBooking::count());
    }

    public function test_shifts_cannot_be_booked_when_the_event_takes_open_interest(): void
    {
        $this->event->update(['staffing_mode' => 'open_interest']);

        $this->book(User::factory()->controller()->create(), $this->towerEarly)->assertSessionHasErrors('booking');
    }

    public function test_a_shift_cannot_be_booked_against_another_events_occurrence(): void
    {
        $other = Event::factory()->withShifts()->create();

        $this->book(User::factory()->controller()->create(), $this->towerEarly, $other->occurrences()->sole())
            ->assertSessionHasErrors('booking');
    }

    public function test_controllers_can_cancel_their_own_booking_until_the_event_starts(): void
    {
        $controller = User::factory()->controller()->create();
        $booking = ShiftBooking::create(['position_shift_id' => $this->towerEarly->id, 'event_occurrence_id' => $this->occurrence->id, 'user_id' => $controller->id]);

        $this->actingAs(User::factory()->controller()->create())->delete("/bookings/{$booking->id}")->assertForbidden();

        $this->travelTo(Carbon::parse('2026-11-10 16:30', 'UTC'));
        $this->actingAs($controller)->delete("/bookings/{$booking->id}")->assertForbidden();

        $this->travelTo(Carbon::parse('2026-11-10 15:59', 'UTC'));
        $this->actingAs($controller)->delete("/bookings/{$booking->id}")->assertRedirect();

        $this->assertModelMissing($booking);
    }

    public function test_staff_can_remove_any_booking_during_the_event(): void
    {
        $booking = ShiftBooking::create(['position_shift_id' => $this->towerEarly->id, 'event_occurrence_id' => $this->occurrence->id, 'user_id' => User::factory()->controller()->create()->id]);

        $this->travelTo(Carbon::parse('2026-11-10 17:00', 'UTC'));
        $this->actingAs(User::factory()->staff()->create())->delete("/bookings/{$booking->id}")->assertRedirect();

        $this->assertModelMissing($booking);
    }

    public function test_everyone_can_see_who_booked_each_shift(): void
    {
        $controller = User::factory()->controller()->create(['name' => 'Jane Controller', 'vatsim_cid' => 1234567]);
        ShiftBooking::create(['position_shift_id' => $this->towerEarly->id, 'event_occurrence_id' => $this->occurrence->id, 'user_id' => $controller->id]);

        $this->get("/events/{$this->event->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('staffing.mode', 'shifts')
                ->where('staffing.bookingOpen', true)
                ->where('staffing.positions.0.callsign', 'EKCH_TWR')
                ->where('staffing.positions.0.shifts.0.time', '16:00–18:00z')
                ->where('staffing.positions.0.shifts.0.booking.controller.name', 'Jane Controller')
                ->where('staffing.positions.0.shifts.0.booking.controller.cid', 1234567)
                ->where('staffing.positions.0.shifts.0.booking.isMine', false)
                ->where('staffing.positions.0.shifts.1.booking', null)
            );
    }
}
