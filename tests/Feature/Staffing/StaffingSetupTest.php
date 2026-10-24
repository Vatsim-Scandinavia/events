<?php

namespace Tests\Feature\Staffing;

use App\Enums\StaffingMode;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaffingSetupTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-01 12:00', 'UTC'));

        // 22:00z on 10 November until 02:00z the next day.
        $this->event = Event::factory()->create([
            'starts_at' => Carbon::parse('2026-11-10 22:00', 'UTC'),
            'ends_at' => Carbon::parse('2026-11-11 02:00', 'UTC'),
        ]);
        $this->staff = User::factory()->staff()->create();
    }

    public function test_every_event_gets_an_occurrence_on_its_own_dates(): void
    {
        $occurrence = $this->event->occurrences()->sole();

        $this->assertTrue($occurrence->starts_at->eq($this->event->starts_at));
        $this->assertTrue($occurrence->ends_at->eq($this->event->ends_at));
    }

    public function test_staff_can_choose_the_staffing_mode_and_interest_window(): void
    {
        $this->actingAs($this->staff)->put("/events/{$this->event->id}/staffing", [
            'mode' => 'open_interest',
            'interest_opens_hours' => 240,
            'interest_closes_hours' => 24,
        ])->assertSessionHasNoErrors();

        $this->event->refresh();
        $this->assertSame(StaffingMode::OpenInterest, $this->event->staffing_mode);
        $this->assertSame(240, $this->event->interest_opens_hours);
        $this->assertSame(24, $this->event->interest_closes_hours);
    }

    public function test_the_interest_window_must_close_after_it_opens(): void
    {
        $this->actingAs($this->staff)->put("/events/{$this->event->id}/staffing", [
            'mode' => 'open_interest',
            'interest_opens_hours' => 24,
            'interest_closes_hours' => 48,
        ])->assertSessionHasErrors('interest_closes_hours');
    }

    /**
     * Save the staffing plan as the staff page does.
     *
     * @param  array<string, mixed>  $plan
     */
    private function savePlan(array $plan, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->staff)->put("/events/{$this->event->id}/staffing/plan", [
            'occurrence_id' => $this->event->occurrences()->sole()->id,
            ...$plan,
        ]);
    }

    private function switchMode(string $mode)
    {
        return $this->actingAs($this->staff)->put("/events/{$this->event->id}/staffing", [
            'mode' => $mode,
            'interest_opens_hours' => 336,
            'interest_closes_hours' => 48,
        ]);
    }

    public function test_switching_away_from_shifts_clears_shifts_and_bookings_but_keeps_positions(): void
    {
        $this->event->update(['staffing_mode' => StaffingMode::Shifts]);
        $tower = $this->event->positions()->create(['callsign' => 'EKCH_TWR']);
        $shift = $tower->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);
        $booking = $shift->bookings()->create(['event_occurrence_id' => $this->event->occurrences()->sole()->id, 'user_id' => User::factory()->controller()->create()->id]);

        // Saving the same mode again changes nothing.
        $this->switchMode('shifts')->assertSessionHasNoErrors();
        $this->assertModelExists($booking);

        $this->switchMode('open_interest')->assertSessionHasNoErrors();
        $this->assertModelMissing($shift);
        $this->assertModelMissing($booking);
        $this->assertModelExists($tower);

        // Switching back doesn't bring them back.
        $this->switchMode('shifts');
        $this->assertSame(0, $tower->shifts()->count());
    }

    public function test_switching_away_from_open_interest_clears_interest_and_the_roster(): void
    {
        $this->event->update(['staffing_mode' => StaffingMode::OpenInterest]);
        $tower = $this->event->positions()->create(['callsign' => 'EKCH_TWR']);
        $occurrence = $this->event->occurrences()->sole();
        $controller = User::factory()->controller()->create();
        $interest = $occurrence->interests()->create(['user_id' => $controller->id]);
        $interest->windows()->create(['starts_at' => $occurrence->starts_at, 'ends_at' => $occurrence->ends_at]);
        $entry = $occurrence->rosterEntries()->create(['event_position_id' => $tower->id, 'user_id' => $controller->id, 'starts_at' => $occurrence->starts_at, 'ends_at' => $occurrence->ends_at]);
        $occurrence->update(['roster_published_at' => now()]);

        // A past date's roster is kept as a record.
        $past = $this->event->occurrences()->create(['starts_at' => now()->subWeek(), 'ends_at' => now()->subWeek()->addHours(4), 'roster_published_at' => now()->subWeek()]);
        $archived = $past->rosterEntries()->create(['event_position_id' => $tower->id, 'user_id' => $controller->id, 'starts_at' => $past->starts_at, 'ends_at' => $past->ends_at]);

        $this->switchMode('shifts')->assertSessionHasNoErrors();

        $this->assertModelMissing($interest);
        $this->assertModelMissing($entry);
        $this->assertNull($occurrence->fresh()->roster_published_at);
        $this->assertModelExists($archived);
        $this->assertModelExists($tower);
    }

    public function test_switching_staffing_off_removes_the_positions_too(): void
    {
        $this->event->update(['staffing_mode' => StaffingMode::Shifts]);
        $tower = $this->event->positions()->create(['callsign' => 'EKCH_TWR']);
        $tower->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);

        $this->switchMode('none')->assertSessionHasNoErrors();

        $this->assertModelMissing($tower);
        $this->assertSame(StaffingMode::None, $this->event->fresh()->staffing_mode);
    }

    public function test_positions_cannot_be_planned_without_a_staffing_mode(): void
    {
        $this->savePlan(['positions' => [['key' => -1, 'callsign' => 'EKCH_TWR']]])->assertSessionHasErrors('plan');

        $this->assertSame(0, $this->event->positions()->count());
    }

    public function test_positions_and_their_shifts_are_saved_together(): void
    {
        $this->event->update(['staffing_mode' => StaffingMode::Shifts]);

        $this->savePlan([
            'positions' => [['key' => -1, 'callsign' => 'ekch_twr', 'frequency' => '118.105'], ['key' => -2, 'callsign' => 'EKCH_APP']],
            'shifts' => [
                ['position' => -1, 'start' => 0, 'end' => 120],
                ['position' => -1, 'start' => 120, 'end' => 240],
                ['position' => -2, 'start' => 0, 'end' => 120],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(['EKCH_TWR', 'EKCH_APP'], $this->event->positions()->pluck('callsign')->all());
        $tower = $this->event->positions()->firstWhere('callsign', 'EKCH_TWR');
        $this->assertSame([[0, 120], [120, 240]], $tower->shifts->map(fn ($shift) => [$shift->starts_offset, $shift->ends_offset])->all());
        $this->assertSame('118.105', $tower->frequency);
    }

    public function test_nothing_is_saved_when_any_part_of_the_plan_breaks_a_rule(): void
    {
        $this->event->update(['staffing_mode' => StaffingMode::Shifts]);
        $tower = $this->event->positions()->create(['callsign' => 'EKCH_TWR']);
        $shift = $tower->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);

        // Moving the existing shift is fine, but the new position's shifts overlap.
        $this->savePlan([
            'positions' => [['key' => $tower->id, 'callsign' => 'EKCH_TWR'], ['key' => -1, 'callsign' => 'EKCH_APP']],
            'shifts' => [
                ['id' => $shift->id, 'position' => $tower->id, 'start' => 60, 'end' => 180],
                ['position' => -1, 'start' => 0, 'end' => 120],
                ['position' => -1, 'start' => 90, 'end' => 150],
            ],
        ])->assertSessionHasErrors(['plan' => 'Two shifts on EKCH_APP overlap.']);

        $this->assertSame(1, $this->event->positions()->count());
        $this->assertSame(0, $shift->fresh()->starts_offset);
    }

    public function test_callsigns_must_be_valid_and_unique(): void
    {
        $this->event->update(['staffing_mode' => StaffingMode::OpenInterest]);

        $this->savePlan(['positions' => [['key' => -1, 'callsign' => 'tower']]])->assertSessionHasErrors('plan');
        $this->savePlan(['positions' => [['key' => -1, 'callsign' => 'EKCH_TWR'], ['key' => -2, 'callsign' => 'ekch_twr']]])
            ->assertSessionHasErrors(['plan' => 'EKCH_TWR is listed twice.']);
        $this->savePlan(['positions' => [['key' => -1, 'callsign' => 'EKCH_TWR', 'frequency' => '99.1']]])->assertSessionHasErrors('plan');

        $this->assertSame(0, $this->event->positions()->count());
    }

    public function test_shifts_must_fall_within_the_event(): void
    {
        $this->event->update(['staffing_mode' => StaffingMode::Shifts]);

        $this->savePlan([
            'positions' => [['key' => -1, 'callsign' => 'EKCH_TWR']],
            'shifts' => [['position' => -1, 'start' => 180, 'end' => 300]],
        ])->assertSessionHasErrors('plan');

        $this->assertSame(0, $this->event->positions()->count());
    }

    public function test_shifts_on_different_positions_may_overlap(): void
    {
        $this->event->update(['staffing_mode' => StaffingMode::Shifts]);

        $this->savePlan([
            'positions' => [['key' => -1, 'callsign' => 'EKCH_TWR'], ['key' => -2, 'callsign' => 'EKCH_APP']],
            'shifts' => [['position' => -1, 'start' => 0, 'end' => 120], ['position' => -2, 'start' => 0, 'end' => 120]],
        ])->assertSessionHasNoErrors();
    }

    public function test_shifts_and_positions_left_out_of_the_plan_are_removed(): void
    {
        $this->event->update(['staffing_mode' => StaffingMode::Shifts]);
        $tower = $this->event->positions()->create(['callsign' => 'EKCH_TWR']);
        $approach = $this->event->positions()->create(['callsign' => 'EKCH_APP']);
        $keep = $tower->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);
        $drop = $tower->shifts()->create(['starts_offset' => 120, 'ends_offset' => 240]);
        $approach->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);

        $this->savePlan([
            'positions' => [['key' => $tower->id, 'callsign' => 'EKCH_TWR']],
            'shifts' => [['id' => $keep->id, 'position' => $tower->id, 'start' => 30, 'end' => 150]],
        ])->assertSessionHasNoErrors();

        $this->assertModelMissing($approach);
        $this->assertModelMissing($drop);
        $this->assertSame([30, 150], [$keep->fresh()->starts_offset, $keep->fresh()->ends_offset]);
    }

    public function test_a_shift_of_another_event_cannot_be_moved_through_this_plan(): void
    {
        $this->event->update(['staffing_mode' => StaffingMode::Shifts]);
        $tower = $this->event->positions()->create(['callsign' => 'EKCH_TWR']);
        $otherShift = Event::factory()->create()->positions()->create(['callsign' => 'ESSA_TWR'])
            ->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);

        $this->savePlan([
            'positions' => [['key' => $tower->id, 'callsign' => 'EKCH_TWR']],
            'shifts' => [['id' => $otherShift->id, 'position' => $tower->id, 'start' => 60, 'end' => 180]],
        ])->assertSessionHasErrors(['plan' => 'A shift on EKCH_TWR was removed by someone else. Reload the page and try again.']);

        $otherShift->refresh();
        $this->assertSame([0, 120], [$otherShift->starts_offset, $otherShift->ends_offset]);
        $this->assertNotSame($tower->id, $otherShift->event_position_id);
    }

    public function test_moving_or_removing_a_booked_shift_unbooks_it(): void
    {
        $this->event->update(['staffing_mode' => StaffingMode::Shifts]);
        $tower = $this->event->positions()->create(['callsign' => 'EKCH_TWR']);
        $first = $tower->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);
        $second = $tower->shifts()->create(['starts_offset' => 120, 'ends_offset' => 240]);
        $occurrence = $this->event->occurrences()->sole();
        $firstBooking = $first->bookings()->create(['event_occurrence_id' => $occurrence->id, 'user_id' => User::factory()->controller()->create()->id]);
        $secondBooking = $second->bookings()->create(['event_occurrence_id' => $occurrence->id, 'user_id' => User::factory()->controller()->create()->id]);
        $positions = [['key' => $tower->id, 'callsign' => 'EKCH_TWR']];

        // Saving a booked shift where it is keeps the booking.
        $this->savePlan(['positions' => $positions, 'shifts' => [
            ['id' => $first->id, 'position' => $tower->id, 'start' => 0, 'end' => 120],
            ['id' => $second->id, 'position' => $tower->id, 'start' => 120, 'end' => 240],
        ]])->assertSessionHasNoErrors();
        $this->assertModelExists($firstBooking);

        // Moving it releases the booking; removing the other removes its booking too.
        $this->savePlan(['positions' => $positions, 'shifts' => [['id' => $first->id, 'position' => $tower->id, 'start' => 60, 'end' => 180]]])
            ->assertSessionHasNoErrors();

        $this->assertSame(60, $first->fresh()->starts_offset);
        $this->assertModelMissing($firstBooking);
        $this->assertModelMissing($secondBooking);
    }

    public function test_shift_positions_stay_hidden_from_controllers_until_they_have_a_shift(): void
    {
        $this->event->update(['staffing_mode' => StaffingMode::Shifts]);

        $this->savePlan(['positions' => [['key' => -1, 'callsign' => 'EKCH_TWR']]])->assertSessionHasNoErrors();
        $position = $this->event->positions()->sole();

        $this->actingAsGuest();
        $this->get("/events/{$this->event->id}")->assertInertia(fn (Assert $page) => $page->has('staffing.positions', 0));

        $position->shifts()->create(['starts_offset' => 0, 'ends_offset' => 120]);
        $this->get("/events/{$this->event->id}")->assertInertia(fn (Assert $page) => $page->has('staffing.positions', 1));
    }

    public function test_only_event_staff_can_set_up_staffing(): void
    {
        $controller = User::factory()->controller()->create();
        $this->event->update(['staffing_mode' => StaffingMode::Shifts]);

        $this->actingAs($controller)->get("/events/{$this->event->id}/staffing")->assertForbidden();
        $this->actingAs($controller)->put("/events/{$this->event->id}/staffing", ['mode' => 'shifts'])->assertForbidden();
        $this->savePlan(['positions' => [['key' => -1, 'callsign' => 'EKCH_APP']]], $controller)->assertForbidden();

        $this->assertSame(0, $this->event->positions()->count());
    }
}
