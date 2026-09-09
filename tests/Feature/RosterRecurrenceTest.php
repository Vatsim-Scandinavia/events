<?php

namespace Tests\Feature;

use App\Actions\Authorization\UpdateRoleAssignments;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventCancellation;
use App\Models\EventRoster;
use App\Models\RosterBooking;
use App\Models\RosterInterest;
use App\Models\RosterPosition;
use App\Models\RosterShift;
use App\Models\RosterSlot;
use App\Models\Team;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RosterRecurrenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_same_slot_has_independent_bookings_on_each_date_and_withdrawal_only_affects_the_selected_date(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $roster = EventRoster::factory()->for(Event::factory()->weekly())->open()->create();
        $slot = $this->slot($roster);
        $first = $this->member($roster->event->owner, RoleName::Controller);
        $second = $this->member(Team::factory()->create(), RoleName::Controller);
        $this->actingAs($first)->post(route('roster.bookings.store', [$roster, $slot]), ['occurrence_date' => '2026-10-04'])->assertSessionHasNoErrors();

        $this->actingAs($second)->get(route('events.roster.show', [$roster->event, '2026-10-11']))->assertInertia(fn (Assert $page) => $page
            ->where('roster.id', $roster->id)->where('roster.shifts.0.slots.0.booking', null)->where('roster.shifts.0.slots.0.can_book', true));
        $this->post(route('roster.bookings.store', [$roster, $slot]), ['occurrence_date' => '2026-10-11'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roster_bookings', ['slot_id' => $slot->id, 'occurrence_date' => '2026-10-04', 'user_cid' => $first->cid, 'starts_at' => '2026-10-04 18:00:00']);
        $this->assertDatabaseHas('roster_bookings', ['slot_id' => $slot->id, 'occurrence_date' => '2026-10-11', 'user_cid' => $second->cid, 'starts_at' => '2026-10-11 18:00:00']);
        $this->assertDatabaseCount('event_rosters', 1);
        $this->assertDatabaseCount('roster_slots', 1);

        $this->delete(route('roster.bookings.destroy', [$roster, $slot]), ['occurrence_date' => '2026-10-11'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roster_bookings', ['slot_id' => $slot->id, 'occurrence_date' => '2026-10-04', 'user_cid' => $first->cid]);
        $this->assertDatabaseMissing('roster_bookings', ['slot_id' => $slot->id, 'occurrence_date' => '2026-10-11']);
    }

    public function test_interest_is_created_updated_and_withdrawn_independently_for_each_occurrence(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $roster = EventRoster::factory()->for(Event::factory()->weekly())->openInterest()->open()->create();
        $position = RosterPosition::factory()->create(['roster_id' => $roster->id]);
        $controller = $this->member($roster->event->owner, RoleName::Controller);
        $this->actingAs($controller)->put(route('roster.interest.update', $roster), $this->interest($position, '2026-10-04'))->assertSessionHasNoErrors();

        $this->get(route('events.roster.show', [$roster->event, '2026-10-11']))->assertInertia(fn (Assert $page) => $page->has('roster.interests', 0));
        $this->put(route('roster.interest.update', $roster), $this->interest($position, '2026-10-11'))->assertSessionHasNoErrors();
        $this->put(route('roster.interest.update', $roster), $this->interest($position, '2026-10-11', '19:00', '21:00'))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('roster_interests', 2);
        $earlier = RosterInterest::where('occurrence_date', '2026-10-04')->firstOrFail();
        $later = RosterInterest::where('occurrence_date', '2026-10-11')->firstOrFail();
        $this->assertSame('2026-10-04T18:00', $earlier->availability[0]['starts_at']);
        $this->assertSame('2026-10-11T19:00', $later->availability[0]['starts_at']);
        $this->get(route('events.roster.show', [$roster->event, '2026-10-04']))->assertInertia(fn (Assert $page) => $page
            ->has('roster.interests', 1)->where('roster.interests.0.id', $earlier->id));

        $this->delete(route('roster.interest.destroy', $roster), ['occurrence_date' => '2026-10-11'])->assertSessionHasNoErrors();

        $this->assertModelExists($earlier);
        $this->assertModelMissing($later);
    }

    public function test_default_roster_keeps_an_ongoing_occurrence_and_rolls_forward_exactly_when_it_ends(): void
    {
        $this->travelTo('2026-10-04 20:59:59');
        $roster = EventRoster::factory()->for(Event::factory()->weekly())->open()->create();
        $slot = $this->slot($roster);
        $controller = $this->member($roster->event->owner, RoleName::Controller);
        RosterBooking::factory()->create(['roster_id' => $roster->id, 'slot_id' => $slot->id, 'user_cid' => $controller->cid, 'occurrence_date' => '2026-10-04']);

        $this->actingAs($controller)->get(route('events.roster.show', $roster->event))->assertInertia(fn (Assert $page) => $page
            ->where('occurrence.date', '2026-10-04')->where('autoSelectOccurrence', true)
            ->where('roster.shifts.0.slots.0.booking.cid', $controller->cid));
        $this->travelTo('2026-10-04 21:00:00');
        $this->get(route('events.roster.show', $roster->event))->assertInertia(fn (Assert $page) => $page
            ->where('occurrence.date', '2026-10-11')->where('roster.shifts.0.slots.0.booking', null));
        $this->get(route('events.roster.show', [$roster->event, '2026-10-04']))->assertInertia(fn (Assert $page) => $page
            ->where('occurrence.date', '2026-10-04')->where('autoSelectOccurrence', false)
            ->where('roster.shifts.0.slots.0.booking.cid', $controller->cid));

        $this->assertDatabaseCount('roster_bookings', 1);
    }

    public function test_default_roster_skips_more_than_one_page_of_cancelled_occurrences(): void
    {
        $this->travelTo('2026-10-05 12:00:00');
        $roster = EventRoster::factory()->for(Event::factory()->weekly())->open()->create();
        $this->slot($roster);
        foreach (['2026-10-11', '2026-10-18', '2026-10-25', '2026-11-01', '2026-11-08', '2026-11-15', '2026-11-22', '2026-11-29', '2026-12-06', '2026-12-13', '2026-12-20', '2026-12-27', '2027-01-03'] as $date) {
            EventCancellation::factory()->for($roster->event)->create(['occurrence_date' => $date]);
        }

        $this->actingAs($this->member($roster->event->owner, RoleName::Controller))->get(route('events.roster.show', $roster->event))->assertInertia(fn (Assert $page) => $page
            ->where('occurrence.date', '2027-01-10')->where('occurrence.status', 'scheduled'));
        $this->get(route('events.roster.show', [$roster->event, '2026-10-04']))->assertInertia(fn (Assert $page) => $page
            ->where('nextOccurrenceDate', '2027-01-10'));
    }

    public function test_a_completed_series_keeps_its_latest_booked_occurrence_available_as_history(): void
    {
        $this->travelTo('2026-10-12 12:00:00');
        $roster = EventRoster::factory()->for(Event::factory()->weekly()->state(['recurrence_until' => '2026-10-11']))->open()->create();
        $slot = $this->slot($roster);
        $controller = $this->member($roster->event->owner, RoleName::Controller);
        RosterBooking::factory()->create(['roster_id' => $roster->id, 'slot_id' => $slot->id, 'user_cid' => $controller->cid, 'occurrence_date' => '2026-10-11']);

        $this->actingAs($controller)->get(route('events.roster.show', $roster->event))->assertInertia(fn (Assert $page) => $page
            ->where('occurrence.date', '2026-10-11')->where('occurrence.has_ended', true)->where('nextOccurrenceDate', null)
            ->where('canParticipate', false)->where('roster.shifts.0.slots.0.booking.cid', $controller->cid));
    }

    public function test_stale_booking_and_interest_forms_do_not_submit_to_the_next_occurrence(): void
    {
        $this->travelTo('2026-10-05 12:00:00');
        $roster = EventRoster::factory()->for(Event::factory()->weekly())->open()->create();
        $slot = $this->slot($roster);
        $interestRoster = EventRoster::factory()->for(Event::factory()->weekly())->openInterest()->open()->create();
        $position = RosterPosition::factory()->create(['roster_id' => $interestRoster->id]);
        $controller = $this->member($roster->event->owner, RoleName::Controller);
        $auditCount = AuditLog::count();

        $this->actingAs($controller)->post(route('roster.bookings.store', [$roster, $slot]), ['occurrence_date' => '2026-10-04'])->assertForbidden();
        $this->put(route('roster.interest.update', $interestRoster), $this->interest($position, '2026-10-04'))->assertForbidden();

        $this->assertDatabaseCount('roster_bookings', 0);
        $this->assertDatabaseCount('roster_interests', 0);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_auto_selected_booking_returns_to_the_current_roster_without_retargeting_stale_forms(): void
    {
        $this->travelTo('2026-10-04 17:00:00');
        $roster = EventRoster::factory()->for(Event::factory()->weekly())->open()->create();
        $slot = $this->slot($roster);
        $controller = $this->member($roster->event->owner, RoleName::Controller);

        $this->actingAs($controller)->post(route('roster.bookings.store', [$roster, $slot]), ['occurrence_date' => '2026-10-04', 'return_to_current' => true])
            ->assertSessionHasNoErrors()->assertRedirectToRoute('events.roster.show', $roster->event);
        $this->travelTo('2026-10-04 21:00:00');
        $this->get(route('events.roster.show', $roster->event))->assertInertia(fn (Assert $page) => $page
            ->where('occurrence.date', '2026-10-11')->where('autoSelectOccurrence', true)->where('roster.shifts.0.slots.0.booking', null));
        $auditCount = AuditLog::count();

        $this->post(route('roster.bookings.store', [$roster, $slot]), ['occurrence_date' => '2026-10-04', 'return_to_current' => true])->assertForbidden();

        $this->assertDatabaseCount('roster_bookings', 1);
        $this->assertDatabaseHas('roster_bookings', ['slot_id' => $slot->id, 'occurrence_date' => '2026-10-04', 'user_cid' => $controller->cid]);
        $this->assertDatabaseMissing('roster_bookings', ['slot_id' => $slot->id, 'occurrence_date' => '2026-10-11']);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_participation_writes_require_an_explicit_occurrence_date(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $roster = EventRoster::factory()->for(Event::factory()->weekly())->open()->create();
        $slot = $this->slot($roster);
        $controller = $this->member($roster->event->owner, RoleName::Controller);
        $auditCount = AuditLog::count();

        $this->actingAs($controller)->post(route('roster.bookings.store', [$roster, $slot]))->assertSessionHasErrors('occurrence_date');
        $this->delete(route('roster.bookings.destroy', [$roster, $slot]))->assertSessionHasErrors('occurrence_date');
        $this->put(route('roster.interest.update', $roster), ['position_ids' => [1], 'availability' => [['starts_at' => '2026-10-04T18:00', 'ends_at' => '2026-10-04T19:00']]])->assertSessionHasErrors('occurrence_date');
        $this->delete(route('roster.interest.destroy', $roster))->assertSessionHasErrors('occurrence_date');

        $this->assertDatabaseCount('roster_bookings', 0);
        $this->assertDatabaseCount('roster_interests', 0);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_shared_slot_times_follow_local_wall_times_across_autumn_dst(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->weekly()->create(['timezone' => 'Europe/Copenhagen', 'local_start' => '2026-10-18T18:00', 'local_end' => '2026-10-18T21:00', 'starts_at' => '2026-10-18 16:00:00', 'ends_at' => '2026-10-18 19:00:00']);
        $coordinator = $this->member($event->owner, RoleName::EventCoordinator);
        $this->actingAs($coordinator)->put(route('events.roster.update', $event), $this->configuration('2026-10-18', '2026-10-18T16:00', '2026-10-18T17:00'))->assertSessionHasNoErrors();
        $roster = EventRoster::firstOrFail();
        $slot = RosterSlot::firstOrFail();

        $this->get(route('events.roster.show', [$event, '2026-10-25']))->assertInertia(fn (Assert $page) => $page
            ->where('roster.shifts.0.slots.0.starts_at', '2026-10-25T17:00')->where('roster.shifts.0.slots.0.ends_at', '2026-10-25T18:00'));
        $controller = $this->member($event->owner, RoleName::Controller);
        $this->actingAs($controller)->post(route('roster.bookings.store', [$roster, $slot]), ['occurrence_date' => '2026-10-25'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roster_slots', ['id' => $slot->id, 'start_day_offset' => 0, 'start_time' => '18:00', 'end_time' => '19:00']);
        $this->assertDatabaseHas('roster_bookings', ['slot_id' => $slot->id, 'occurrence_date' => '2026-10-25', 'starts_at' => '2026-10-25 17:00:00', 'ends_at' => '2026-10-25 18:00:00']);
    }

    public function test_overnight_template_offsets_follow_local_dates_across_dst(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->weekly()->create(['timezone' => 'Europe/Copenhagen', 'local_start' => '2026-10-18T23:00', 'local_end' => '2026-10-19T03:00', 'starts_at' => '2026-10-18 21:00:00', 'ends_at' => '2026-10-19 01:00:00']);
        $coordinator = $this->member($event->owner, RoleName::EventCoordinator);
        $this->actingAs($coordinator)->put(route('events.roster.update', $event), $this->configuration('2026-10-18', '2026-10-18T21:30', '2026-10-18T23:30'))->assertSessionHasNoErrors();

        $this->get(route('events.roster.show', [$event, '2026-10-25']))->assertInertia(fn (Assert $page) => $page
            ->where('roster.shifts.0.slots.0.starts_at', '2026-10-25T22:30')->where('roster.shifts.0.slots.0.ends_at', '2026-10-26T00:30'));

        $this->assertDatabaseHas('roster_slots', ['start_day_offset' => 0, 'start_time' => '23:30', 'end_day_offset' => 1, 'end_time' => '01:30']);
    }

    public function test_a_slot_in_a_spring_dst_gap_is_unavailable_without_changing_the_event_or_template(): void
    {
        $this->travelTo('2026-03-01 12:00:00');
        $event = Event::factory()->weekly()->create(['timezone' => 'Europe/Copenhagen', 'local_start' => '2026-03-22T01:00', 'local_end' => '2026-03-22T04:00', 'starts_at' => '2026-03-22 00:00:00', 'ends_at' => '2026-03-22 03:00:00']);
        $roster = EventRoster::factory()->for($event)->open()->create();
        $slot = $this->slot($roster, ['start_time' => '02:30', 'end_time' => '03:30']);
        $controller = $this->member($event->owner, RoleName::Controller);
        $auditCount = AuditLog::count();

        $this->actingAs($controller)->get(route('events.roster.show', [$event, '2026-03-29']))->assertInertia(fn (Assert $page) => $page
            ->where('occurrence.status', 'scheduled')->where('roster.shifts.0.slots.0.starts_at', null)
            ->where('roster.shifts.0.slots.0.is_unavailable', true)->where('roster.shifts.0.slots.0.can_book', false));
        $this->post(route('roster.bookings.store', [$roster, $slot]), ['occurrence_date' => '2026-03-29'])->assertSessionHasErrors('booking');

        $this->assertDatabaseHas('roster_slots', ['id' => $slot->id, 'start_time' => '02:30']);
        $this->assertDatabaseCount('roster_bookings', 0);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_future_bookings_protect_shared_slots_even_when_editing_another_occurrence(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $roster = EventRoster::factory()->for(Event::factory()->weekly())->open()->create();
        $slot = $this->slot($roster);
        RosterBooking::factory()->create(['roster_id' => $roster->id, 'slot_id' => $slot->id, 'occurrence_date' => '2026-10-11']);
        $coordinator = $this->member($roster->event->owner, RoleName::EventCoordinator);
        $payload = $this->configuration('2026-10-04', '2026-10-04T18:00', '2026-10-04T20:00');
        $payload['shifts'][0]['id'] = $slot->shift_id;
        $payload['shifts'][0]['slots'][0]['id'] = $slot->id;
        $auditCount = AuditLog::count();

        $this->actingAs($coordinator)->get(route('events.roster.show', [$roster->event, '2026-10-04']))->assertInertia(fn (Assert $page) => $page
            ->where('roster.mode_locked', true)->where('roster.shifts.0.slots.0.is_locked', true)->where('roster.shifts.0.slots.0.booking', null));
        $this->put(route('events.roster.update', $roster->event), $payload)->assertSessionHasErrors('shifts.0.slots.0');

        $this->assertDatabaseHas('roster_slots', ['id' => $slot->id, 'end_time' => '19:00']);
        $this->assertDatabaseCount('roster_bookings', 1);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_past_booking_snapshots_survive_replacing_the_shared_roster_mode(): void
    {
        $this->travelTo('2026-10-05 12:00:00');
        $roster = EventRoster::factory()->for(Event::factory()->weekly())->open()->create();
        $slot = $this->slot($roster);
        $booking = RosterBooking::factory()->create(['roster_id' => $roster->id, 'slot_id' => $slot->id, 'occurrence_date' => '2026-10-04', 'callsign' => 'EKCH_A_TWR', 'shift_name' => 'Early', 'starts_at' => '2026-10-04 18:00:00', 'ends_at' => '2026-10-04 19:00:00']);
        $coordinator = $this->member($roster->event->owner, RoleName::EventCoordinator);

        $this->actingAs($coordinator)->put(route('events.roster.update', $roster->event), ['occurrence_date' => '2026-10-11', 'mode' => 'open_interest', 'is_open' => true, 'shifts' => [], 'positions' => [['callsign' => 'EKCH_APP']]])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roster_bookings', ['id' => $booking->id, 'slot_id' => null, 'roster_id' => $roster->id, 'occurrence_date' => '2026-10-04', 'callsign' => 'EKCH_A_TWR', 'shift_name' => 'Early', 'starts_at' => '2026-10-04 18:00:00']);
        $this->assertModelMissing($slot);
        $this->assertDatabaseHas('event_rosters', ['id' => $roster->id, 'mode' => 'open_interest']);
    }

    public function test_future_interest_snapshots_lock_the_mode_even_when_their_original_positions_are_gone(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $roster = EventRoster::factory()->for(Event::factory()->weekly())->openInterest()->open()->create();
        $interest = RosterInterest::factory()->create(['roster_id' => $roster->id, 'occurrence_date' => '2026-10-11', 'position_ids' => [], 'position_callsigns' => ['EKCH_APP'], 'occurrence_ends_at' => '2026-10-11 21:00:00']);
        $coordinator = $this->member($roster->event->owner, RoleName::EventCoordinator);
        $auditCount = AuditLog::count();

        $this->actingAs($coordinator)->get(route('events.roster.show', [$roster->event, '2026-10-04']))->assertInertia(fn (Assert $page) => $page
            ->where('roster.mode_locked', true)->has('roster.interests', 0));
        $this->put(route('events.roster.update', $roster->event), $this->configuration('2026-10-04', '2026-10-04T18:00', '2026-10-04T19:00'))->assertSessionHasErrors('mode');

        $this->assertModelExists($interest);
        $this->assertDatabaseHas('event_rosters', ['id' => $roster->id, 'mode' => 'open_interest']);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_preserved_bookings_without_a_template_slot_can_only_be_withdrawn_by_their_controller_for_their_date(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $roster = EventRoster::factory()->for(Event::factory()->weekly())->open()->create();
        $controller = $this->member($roster->event->owner, RoleName::Controller);
        $other = $this->member($roster->event->owner, RoleName::Controller);
        $booking = RosterBooking::factory()->create(['roster_id' => $roster->id, 'slot_id' => null, 'user_cid' => $controller->cid, 'occurrence_date' => '2026-10-04', 'callsign' => 'EKCH_APP', 'shift_name' => 'Old section', 'starts_at' => '2026-10-04 18:00:00', 'ends_at' => '2026-10-04 19:00:00']);
        $auditCount = AuditLog::count();

        $this->actingAs($other)->delete(route('roster.bookings.withdraw', [$roster, $booking]), ['occurrence_date' => '2026-10-04'])->assertForbidden();
        $this->actingAs($controller)->delete(route('roster.bookings.withdraw', [$roster, $booking]), ['occurrence_date' => '2026-10-11'])->assertNotFound();

        $this->assertModelExists($booking);
        $this->assertDatabaseCount('audit_logs', $auditCount);

        $this->delete(route('roster.bookings.withdraw', [$roster, $booking]), ['occurrence_date' => '2026-10-04'])->assertSessionHasNoErrors();

        $this->assertModelMissing($booking);
        $this->assertDatabaseHas('audit_logs', ['subject_type' => 'roster', 'subject_id' => $roster->id, 'event' => 'withdrawn', 'actor_cid' => $controller->cid]);
    }

    /** @param array<string, mixed> $attributes */
    private function slot(EventRoster $roster, array $attributes = []): RosterSlot
    {
        $shift = RosterShift::factory()->create(['roster_id' => $roster->id, 'name' => 'Early']);

        return RosterSlot::factory()->create(['shift_id' => $shift->id, 'callsign' => 'EKCH_A_TWR', 'start_day_offset' => 0, 'start_time' => '18:00', 'end_day_offset' => 0, 'end_time' => '19:00', ...$attributes]);
    }

    /** @return array<string, mixed> */
    private function configuration(string $date, string $start, string $end): array
    {
        return ['occurrence_date' => $date, 'mode' => 'pre_slotted', 'is_open' => true, 'positions' => [], 'shifts' => [['name' => 'Early', 'slots' => [['callsign' => 'EKCH_A_TWR', 'starts_at' => $start, 'ends_at' => $end]]]]];
    }

    /** @return array<string, mixed> */
    private function interest(RosterPosition $position, string $date, string $start = '18:00', string $end = '19:00'): array
    {
        return ['occurrence_date' => $date, 'position_ids' => [$position->id], 'availability' => [['starts_at' => $date.'T'.$start, 'ends_at' => $date.'T'.$end]]];
    }

    private function member(Team $fir, RoleName $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        app(UpdateRoleAssignments::class)->grant($user, $role, $fir);

        return $user;
    }
}
