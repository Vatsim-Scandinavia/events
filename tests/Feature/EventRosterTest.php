<?php

namespace Tests\Feature;

use App\Actions\Authorization\UpdateRoleAssignments;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventCancellation;
use App\Models\EventCollaboration;
use App\Models\EventRoster;
use App\Models\RosterBooking;
use App\Models\RosterInterest;
use App\Models\RosterPosition;
use App\Models\RosterShift;
use App\Models\RosterSlot;
use App\Models\Team;
use App\Models\User;
use App\RoleName;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class EventRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_creates_named_shifts_with_normalized_callsigns_utc_times_and_an_audit(): void
    {
        $event = $this->event();
        $coordinator = $this->member($event->owner);
        $payload = $this->configuration();
        $payload['shifts'][0]['slots'][0]['callsign'] = ' ekch_a_twr ';

        $this->actingAs($coordinator)->put(route('events.roster.update', $event), $payload)
            ->assertSessionHasNoErrors()->assertRedirectToRoute('events.roster.show', [$event, '2026-10-04']);

        $roster = EventRoster::firstOrFail();
        $this->assertDatabaseHas('event_rosters', ['id' => $roster->id, 'event_id' => $event->id, 'mode' => 'pre_slotted', 'is_open' => true]);
        $this->assertDatabaseHas('roster_shifts', ['roster_id' => $roster->id, 'name' => 'Early']);
        $this->assertDatabaseHas('roster_slots', ['callsign' => 'EKCH_A_TWR', 'start_day_offset' => 0, 'start_time' => '18:00', 'end_day_offset' => 0, 'end_time' => '19:00']);
        $this->assertDatabaseHas('audit_logs', ['subject_type' => 'roster', 'subject_id' => $roster->id, 'event' => 'created', 'actor_cid' => $coordinator->cid]);
        $this->get(route('events.roster.show', [$event, '2026-10-04']))->assertInertia(fn (Assert $page) => $page
            ->component('events/roster')->where('canManage', true)->where('roster.shifts.0.slots.0.callsign', 'EKCH_A_TWR'));
    }

    public function test_the_same_position_cannot_be_added_twice_in_one_shift_even_with_adjacent_times(): void
    {
        $event = $this->event();
        $payload = $this->configuration();
        $payload['shifts'][0]['slots'][] = ['callsign' => 'ekch_a_twr', 'starts_at' => '2026-10-04T19:00', 'ends_at' => '2026-10-04T20:00'];
        $this->actingAs($this->member($event->owner));
        $auditCount = AuditLog::count();

        $this->put(route('events.roster.update', $event), $payload)->assertSessionHasErrors('shifts.0.slots.1.callsign');

        $this->assertDatabaseCount('event_rosters', 0);
        $this->assertDatabaseCount('roster_shifts', 0);
        $this->assertDatabaseCount('roster_slots', 0);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_position_times_cannot_overlap_across_shifts_after_case_normalization(): void
    {
        $event = $this->event();
        $payload = $this->configuration();
        $payload['shifts'][] = ['name' => 'Late', 'slots' => [['callsign' => 'ekch_a_twr', 'starts_at' => '2026-10-04T18:55', 'ends_at' => '2026-10-04T20:00']]];
        $this->actingAs($this->member($event->owner));
        $auditCount = AuditLog::count();

        $this->put(route('events.roster.update', $event), $payload)->assertSessionHasErrors('shifts.1.slots.0.starts_at');

        $this->assertDatabaseCount('event_rosters', 0);
        $this->assertDatabaseCount('roster_slots', 0);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_the_same_position_can_have_adjacent_slots_in_different_shifts(): void
    {
        $event = $this->event();
        $payload = $this->configuration();
        $payload['shifts'][] = ['name' => 'Late', 'slots' => [['callsign' => 'EKCH_A_TWR', 'starts_at' => '2026-10-04T19:00', 'ends_at' => '2026-10-04T21:00']]];

        $this->actingAs($this->member($event->owner))->put(route('events.roster.update', $event), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseCount('roster_shifts', 2);
        $this->assertDatabaseCount('roster_slots', 2);
    }

    #[TestWith(['starts_at', '2026-10-04T17:59', 'shifts.0.slots.0.starts_at'])]
    #[TestWith(['ends_at', '2026-10-04T21:01', 'shifts.0.slots.0.starts_at'])]
    #[TestWith(['ends_at', '2026-10-04T18:00', 'shifts.0.slots.0.ends_at'])]
    #[TestWith(['ends_at', '2026-10-04T17:00', 'shifts.0.slots.0.ends_at'])]
    #[TestWith(['starts_at', '2026-10-04T18:00+02:00', 'shifts.0.slots.0.starts_at'])]
    #[TestWith(['callsign', 'not a position', 'shifts.0.slots.0.callsign'])]
    public function test_invalid_slots_are_rejected_without_writes(string $field, string $value, string $error): void
    {
        $event = $this->event();
        $payload = $this->configuration();
        $payload['shifts'][0]['slots'][0][$field] = $value;
        $this->actingAs($this->member($event->owner));
        $auditCount = AuditLog::count();

        $this->put(route('events.roster.update', $event), $payload)->assertSessionHasErrors($error);

        $this->assertDatabaseCount('event_rosters', 0);
        $this->assertDatabaseCount('roster_slots', 0);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_overnight_slots_use_absolute_utc_dates_within_the_local_occurrence(): void
    {
        $event = $this->event(['timezone' => 'Europe/Copenhagen', 'local_start' => '2026-10-04T23:00', 'local_end' => '2026-10-05T03:00', 'starts_at' => '2026-10-04 21:00:00', 'ends_at' => '2026-10-05 01:00:00']);
        $payload = $this->configuration();
        $payload['shifts'][0]['slots'][0] = ['callsign' => 'EKCH_A_TWR', 'starts_at' => '2026-10-04T23:30', 'ends_at' => '2026-10-05T00:30'];

        $this->actingAs($this->member($event->owner))->put(route('events.roster.update', $event), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roster_slots', ['start_day_offset' => 1, 'start_time' => '01:30', 'end_day_offset' => 1, 'end_time' => '02:30']);
    }

    public function test_roster_modes_require_their_own_configuration_and_reject_missing_fields(): void
    {
        $event = $this->event();
        $this->actingAs($this->member($event->owner));
        $auditCount = AuditLog::count();

        $this->put(route('events.roster.update', $event), [])->assertSessionHasErrors(['mode', 'is_open']);
        $this->put(route('events.roster.update', $event), ['occurrence_date' => '2026-10-04', 'mode' => 'invalid', 'is_open' => true])->assertSessionHasErrors('mode');

        $this->assertDatabaseCount('event_rosters', 0);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    #[TestWith([RoleName::EventCoordinator, true, true, false])]
    #[TestWith([RoleName::VaccStaff, true, false, false])]
    #[TestWith([RoleName::Controller, true, false, true])]
    #[TestWith([RoleName::Pilot, false, false, false])]
    #[TestWith([RoleName::Administrator, true, true, false])]
    public function test_roster_permission_matrix(RoleName $role, bool $view, bool $update, bool $participate): void
    {
        $event = $this->event();
        $roster = $this->roster($event);
        $user = $this->member($event->owner, $role);

        $this->assertSame($view, $user->can('view', $roster));
        $this->assertSame($update, $user->can('update', $roster));
        $this->assertSame($participate, $user->can('participate', [$roster, '2026-10-04']));
    }

    public function test_administrator_can_book_only_when_also_holding_a_controller_role(): void
    {
        $event = $this->event();
        $roster = $this->roster($event);
        $slot = $this->slot($roster);
        $administrator = $this->member($event->owner, RoleName::Administrator);
        $auditCount = AuditLog::count();

        $this->actingAs($administrator)->post(route('roster.bookings.store', [$roster, $slot]), ['occurrence_date' => '2026-10-04'])->assertForbidden();

        $this->assertDatabaseMissing('roster_bookings', ['slot_id' => $slot->id, 'occurrence_date' => '2026-10-04']);
        $this->assertDatabaseCount('audit_logs', $auditCount);

        app(UpdateRoleAssignments::class)->grant($administrator, RoleName::Controller, $event->owner);
        $this->post(route('roster.bookings.store', [$roster, $slot]), ['occurrence_date' => '2026-10-04'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roster_bookings', ['slot_id' => $slot->id, 'occurrence_date' => '2026-10-04', 'user_cid' => $administrator->cid]);
    }

    public function test_accepted_collaborator_coordinators_can_configure_rosters_and_controllers_can_participate(): void
    {
        $event = $this->event();
        $collaboration = EventCollaboration::factory()->for($event)->accepted()->create();
        $coordinator = $this->member($collaboration->team);
        $controller = $this->member($collaboration->team, RoleName::Controller);

        $this->actingAs($coordinator)->put(route('events.roster.update', $event), $this->configuration())->assertSessionHasNoErrors();
        $roster = EventRoster::firstOrFail();
        $slot = RosterSlot::firstOrFail();
        $this->actingAs($controller)->post(route('roster.bookings.store', [$roster, $slot]), ['occurrence_date' => '2026-10-04'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roster_bookings', ['slot_id' => $slot->id, 'occurrence_date' => '2026-10-04', 'user_cid' => $controller->cid]);
    }

    public function test_staff_and_controllers_cannot_write_roster_configuration(): void
    {
        $event = $this->event();
        $staff = $this->member($event->owner, RoleName::VaccStaff);
        $controller = $this->member($event->owner, RoleName::Controller);
        $auditCount = AuditLog::count();

        $this->actingAs($staff)->put(route('events.roster.update', $event), $this->configuration())->assertForbidden();
        $this->actingAs($controller)->put(route('events.roster.update', $event), $this->configuration())->assertForbidden();

        $this->assertDatabaseCount('event_rosters', 0);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_controllers_from_any_fir_can_view_and_book_opened_rosters(): void
    {
        $event = $this->event();
        $roster = $this->roster($event);
        $slot = $this->slot($roster);
        $outsider = $this->member(Team::factory()->create(), RoleName::Controller);

        $this->actingAs($outsider)->get(route('events.roster.show', [$event, '2026-10-04']))->assertInertia(fn (Assert $page) => $page
            ->component('events/roster')->where('canManage', false)->where('canParticipate', true));
        $this->post(route('roster.bookings.store', [$roster, $slot]), ['occurrence_date' => '2026-10-04'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roster_bookings', ['slot_id' => $slot->id, 'occurrence_date' => '2026-10-04', 'user_cid' => $outsider->cid]);
    }

    public function test_other_fir_and_pending_collaborator_coordinators_cannot_manage_rosters(): void
    {
        $event = $this->event();
        $pending = EventCollaboration::factory()->for($event)->create();
        $outsider = $this->member(Team::factory()->create());
        $pendingCoordinator = $this->member($pending->team);
        $auditCount = AuditLog::count();

        $this->actingAs($outsider)->get(route('events.roster.show', [$event, '2026-10-04']))->assertNotFound();
        $this->put(route('events.roster.update', $event), $this->configuration())->assertForbidden();
        $this->actingAs($pendingCoordinator)->get(route('events.roster.show', [$event, '2026-10-04']))->assertNotFound();
        $this->put(route('events.roster.update', $event), $this->configuration())->assertForbidden();

        $this->assertDatabaseCount('event_rosters', 0);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_controller_cannot_view_a_roster_that_has_never_opened(): void
    {
        $event = $this->event();
        $roster = $this->roster($event, ['is_open' => false, 'opened_at' => null]);
        $controller = $this->member($event->owner, RoleName::Controller);

        $this->actingAs($controller)->get(route('events.roster.show', [$event, '2026-10-04']))->assertNotFound();

        $this->assertFalse($controller->can('view', $roster));
        $this->assertFalse($controller->can('participate', [$roster, '2026-10-04']));
    }

    public function test_guests_cannot_read_configure_book_or_submit_interest(): void
    {
        $event = $this->event();
        $roster = $this->roster($event);
        $slot = $this->slot($roster);

        $this->get(route('events.roster.show', [$event, '2026-10-04']))->assertRedirectToRoute('login');
        $this->put(route('events.roster.update', $event), $this->configuration())->assertRedirectToRoute('login');
        $this->post(route('roster.bookings.store', [$roster, $slot]), ['occurrence_date' => '2026-10-04'])->assertRedirectToRoute('login');
        $this->delete(route('roster.bookings.destroy', [$roster, $slot]), ['occurrence_date' => '2026-10-04'])->assertRedirectToRoute('login');
        $this->put(route('roster.interest.update', $roster), [])->assertRedirectToRoute('login');
        $this->delete(route('roster.interest.destroy', $roster), ['occurrence_date' => '2026-10-04'])->assertRedirectToRoute('login');

        $this->assertDatabaseMissing('roster_bookings', ['slot_id' => $slot->id, 'occurrence_date' => '2026-10-04']);
        $this->assertDatabaseCount('roster_interests', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_controller_books_for_the_authenticated_cid_and_can_withdraw_with_audits(): void
    {
        $event = $this->event();
        $roster = $this->roster($event);
        $slot = $this->slot($roster);
        $controller = $this->member($event->owner, RoleName::Controller);
        $other = User::factory()->create();

        $this->actingAs($controller)->post(route('roster.bookings.store', [$roster, $slot]), ['occurrence_date' => '2026-10-04', 'user_cid' => $other->cid])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roster_bookings', ['slot_id' => $slot->id, 'occurrence_date' => '2026-10-04', 'user_cid' => $controller->cid]);
        $this->assertDatabaseHas('audit_logs', ['subject_type' => 'roster', 'subject_id' => $roster->id, 'event' => 'booked', 'actor_cid' => $controller->cid]);

        $this->delete(route('roster.bookings.destroy', [$roster, $slot]), ['occurrence_date' => '2026-10-04'])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('roster_bookings', ['slot_id' => $slot->id, 'occurrence_date' => '2026-10-04']);
        $this->assertDatabaseHas('audit_logs', ['subject_type' => 'roster', 'subject_id' => $roster->id, 'event' => 'withdrawn', 'actor_cid' => $controller->cid]);
    }

    public function test_an_occupied_slot_cannot_be_taken_or_withdrawn_by_another_controller(): void
    {
        $event = $this->event();
        $roster = $this->roster($event);
        $bookedController = $this->member($event->owner, RoleName::Controller);
        $slot = $this->slot($roster, ['booking_user_cid' => $bookedController->cid]);
        $other = $this->member($event->owner, RoleName::Controller);
        $auditCount = AuditLog::count();

        $this->actingAs($other)->post(route('roster.bookings.store', [$roster, $slot]), ['occurrence_date' => '2026-10-04'])->assertSessionHasErrors('booking');
        $this->delete(route('roster.bookings.destroy', [$roster, $slot]), ['occurrence_date' => '2026-10-04'])->assertForbidden();

        $this->assertDatabaseHas('roster_bookings', ['slot_id' => $slot->id, 'occurrence_date' => '2026-10-04', 'user_cid' => $bookedController->cid]);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_controller_cannot_book_overlapping_slots_in_another_event(): void
    {
        $event = $this->event();
        $roster = $this->roster($event);
        $controller = $this->member($event->owner, RoleName::Controller);
        $this->slot($roster, ['booking_user_cid' => $controller->cid]);
        $otherEvent = $this->event(['owner_team_id' => $event->owner_team_id]);
        $otherRoster = $this->roster($otherEvent);
        $otherSlot = $this->slot($otherRoster, ['callsign' => 'EKCH_APP', 'starts_at' => '2026-10-04 18:55:00', 'ends_at' => '2026-10-04 20:00:00']);
        $auditCount = AuditLog::count();

        $this->actingAs($controller)->post(route('roster.bookings.store', [$otherRoster, $otherSlot]), ['occurrence_date' => '2026-10-04'])->assertSessionHasErrors('booking');

        $this->assertDatabaseMissing('roster_bookings', ['slot_id' => $otherSlot->id, 'occurrence_date' => '2026-10-04']);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_a_slot_that_has_started_cannot_be_booked_while_the_event_is_still_running(): void
    {
        $event = $this->event();
        $roster = $this->roster($event);
        $slot = $this->slot($roster);
        $controller = $this->member($event->owner, RoleName::Controller);
        $this->travelTo(CarbonImmutable::parse('2026-10-04T18:30:00Z'));
        $auditCount = AuditLog::count();

        $this->actingAs($controller)->post(route('roster.bookings.store', [$roster, $slot]), ['occurrence_date' => '2026-10-04'])->assertInvalid(['booking' => 'This slot has already started.']);
        $this->get(route('events.roster.show', [$event, '2026-10-04']))->assertInertia(fn (Assert $page) => $page
            ->where('canParticipate', true)->where('roster.shifts.0.slots.0.can_book', false));

        $this->assertDatabaseMissing('roster_bookings', ['slot_id' => $slot->id, 'occurrence_date' => '2026-10-04']);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_a_booking_for_a_cancelled_occurrence_does_not_block_a_replacement_booking(): void
    {
        $cancelledEvent = $this->event();
        $cancelledRoster = $this->roster($cancelledEvent);
        $controller = $this->member($cancelledEvent->owner, RoleName::Controller);
        $this->slot($cancelledRoster, ['booking_user_cid' => $controller->cid]);
        EventCancellation::factory()->for($cancelledEvent)->create(['occurrence_date' => '2026-10-04']);
        $event = $this->event();
        $roster = $this->roster($event);
        $slot = $this->slot($roster);

        $this->actingAs($controller)->post(route('roster.bookings.store', [$roster, $slot]), ['occurrence_date' => '2026-10-04'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roster_bookings', ['slot_id' => $slot->id, 'occurrence_date' => '2026-10-04', 'user_cid' => $controller->cid]);
    }

    public function test_repeating_a_booking_does_not_create_another_audit_record(): void
    {
        $event = $this->event();
        $roster = $this->roster($event);
        $slot = $this->slot($roster);
        $controller = $this->member($event->owner, RoleName::Controller);
        $this->actingAs($controller)->post(route('roster.bookings.store', [$roster, $slot]), ['occurrence_date' => '2026-10-04'])->assertSessionHasNoErrors();
        $auditCount = AuditLog::count();

        $this->post(route('roster.bookings.store', [$roster, $slot]), ['occurrence_date' => '2026-10-04'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roster_bookings', ['slot_id' => $slot->id, 'occurrence_date' => '2026-10-04', 'user_cid' => $controller->cid]);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_controller_can_book_an_adjacent_slot(): void
    {
        $event = $this->event();
        $roster = $this->roster($event);
        $controller = $this->member($event->owner, RoleName::Controller);
        $this->slot($roster, ['booking_user_cid' => $controller->cid]);
        $next = $this->slot($roster, ['starts_at' => '2026-10-04 19:00:00', 'ends_at' => '2026-10-04 20:00:00']);

        $this->actingAs($controller)->post(route('roster.bookings.store', [$roster, $next]), ['occurrence_date' => '2026-10-04'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roster_bookings', ['slot_id' => $next->id, 'occurrence_date' => '2026-10-04', 'user_cid' => $controller->cid]);
    }

    #[TestWith(['closed'])]
    #[TestWith(['cancelled'])]
    #[TestWith(['occurrence_cancelled'])]
    #[TestWith(['past'])]
    public function test_unavailable_rosters_reject_new_bookings_but_allow_own_withdrawal(string $state): void
    {
        $event = $this->event();
        $roster = $this->roster($event);
        $controller = $this->member($event->owner, RoleName::Controller);
        $booked = $this->slot($roster, ['booking_user_cid' => $controller->cid]);
        $empty = $this->slot($roster, ['callsign' => 'EKCH_APP']);
        $this->makeUnavailable($event, $roster, $state);
        $auditCount = AuditLog::count();

        $this->actingAs($controller)->post(route('roster.bookings.store', [$roster, $empty]), ['occurrence_date' => '2026-10-04'])->assertForbidden();

        $this->assertDatabaseMissing('roster_bookings', ['slot_id' => $empty->id, 'occurrence_date' => '2026-10-04']);
        $this->assertDatabaseCount('audit_logs', $auditCount);

        $this->delete(route('roster.bookings.destroy', [$roster, $booked]), ['occurrence_date' => '2026-10-04'])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('roster_bookings', ['slot_id' => $booked->id, 'occurrence_date' => '2026-10-04']);
    }

    public function test_slot_ids_cannot_cross_event_rosters(): void
    {
        $event = $this->event(['recurrence' => 'weekly']);
        $roster = $this->roster($event);
        $otherRoster = $this->roster($this->event());
        $otherSlot = $this->slot($otherRoster, ['starts_at' => '2026-10-04 18:00:00', 'ends_at' => '2026-10-04 19:00:00']);
        $controller = $this->member($event->owner, RoleName::Controller);
        $auditCount = AuditLog::count();

        $this->actingAs($controller)->post(route('roster.bookings.store', [$roster, $otherSlot]), ['occurrence_date' => '2026-10-04'])->assertNotFound();
        $this->delete(route('roster.bookings.destroy', [$roster, $otherSlot]), ['occurrence_date' => '2026-10-04'])->assertNotFound();

        $this->assertDatabaseMissing('roster_bookings', ['slot_id' => $otherSlot->id, 'occurrence_date' => '2026-10-04']);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_coordinator_can_rename_a_shift_and_close_a_roster_without_losing_its_booking(): void
    {
        $event = $this->event();
        $roster = $this->roster($event);
        $controller = $this->member($event->owner, RoleName::Controller);
        $slot = $this->slot($roster, ['booking_user_cid' => $controller->cid]);
        $payload = $this->configuration();
        $payload['is_open'] = false;
        $payload['shifts'][0]['id'] = $slot->shift_id;
        $payload['shifts'][0]['name'] = 'Main';
        $payload['shifts'][0]['slots'][0]['id'] = $slot->id;
        $coordinator = $this->member($event->owner);

        $this->actingAs($coordinator)->put(route('events.roster.update', $event), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roster_shifts', ['id' => $slot->shift_id, 'name' => 'Main']);
        $this->assertDatabaseHas('event_rosters', ['id' => $roster->id, 'is_open' => false]);
        $this->assertDatabaseHas('roster_bookings', ['slot_id' => $slot->id, 'occurrence_date' => '2026-10-04', 'user_cid' => $controller->cid]);
        $this->assertDatabaseHas('audit_logs', ['subject_type' => 'roster', 'subject_id' => $roster->id, 'event' => 'updated', 'actor_cid' => $coordinator->cid]);
        $this->actingAs($controller)->get(route('events.roster.show', [$event, '2026-10-04']))->assertInertia(fn (Assert $page) => $page
            ->where('canParticipate', false)->where('roster.is_open', false));
    }

    public function test_coordinator_can_swap_unbooked_callsigns_in_a_shift_while_preserving_slot_ids(): void
    {
        $event = $this->event();
        $roster = $this->roster($event);
        $first = $this->slot($roster);
        $second = RosterSlot::factory()->create(['shift_id' => $first->shift_id, 'callsign' => 'EKCH_APP', 'start_day_offset' => 0, 'start_time' => '18:00', 'end_day_offset' => 0, 'end_time' => '19:00']);
        $payload = $this->configuration();
        $payload['shifts'][0]['id'] = $first->shift_id;
        $payload['shifts'][0]['slots'][0]['id'] = $first->id;
        $payload['shifts'][0]['slots'][0]['callsign'] = 'EKCH_APP';
        $payload['shifts'][0]['slots'][] = ['id' => $second->id, 'callsign' => 'EKCH_A_TWR', 'starts_at' => '2026-10-04T18:00', 'ends_at' => '2026-10-04T19:00'];

        $this->actingAs($this->member($event->owner))->put(route('events.roster.update', $event), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roster_slots', ['id' => $first->id, 'callsign' => 'EKCH_APP']);
        $this->assertDatabaseHas('roster_slots', ['id' => $second->id, 'callsign' => 'EKCH_A_TWR']);
        $this->assertDatabaseCount('roster_slots', 2);
    }

    public function test_coordinator_cannot_change_or_remove_a_booked_slot_or_switch_its_roster_mode(): void
    {
        $event = $this->event();
        $roster = $this->roster($event);
        $controller = $this->member($event->owner, RoleName::Controller);
        $slot = $this->slot($roster, ['booking_user_cid' => $controller->cid]);
        $payload = $this->configuration();
        $payload['shifts'][0]['id'] = $slot->shift_id;
        $payload['shifts'][0]['slots'][0]['id'] = $slot->id;
        $payload['shifts'][0]['slots'][0]['ends_at'] = '2026-10-04T20:00';
        $this->actingAs($this->member($event->owner));
        $auditCount = AuditLog::count();

        $this->put(route('events.roster.update', $event), $payload)->assertSessionHasErrors('shifts.0.slots.0');
        $this->put(route('events.roster.update', $event), ['occurrence_date' => '2026-10-04', 'mode' => 'pre_slotted', 'is_open' => false, 'shifts' => [], 'positions' => []])->assertSessionHasErrors('shifts');
        $this->put(route('events.roster.update', $event), ['occurrence_date' => '2026-10-04', 'mode' => 'open_interest', 'is_open' => false, 'shifts' => [], 'positions' => []])->assertSessionHasErrors('mode');

        $this->assertSame('pre_slotted', $roster->fresh()->mode);
        $this->assertDatabaseHas('roster_slots', ['id' => $slot->id, 'end_time' => '19:00']);
        $this->assertDatabaseHas('roster_bookings', ['slot_id' => $slot->id, 'occurrence_date' => '2026-10-04', 'ends_at' => '2026-10-04 19:00:00', 'user_cid' => $controller->cid]);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_configuration_cannot_claim_shift_or_slot_ids_from_another_event(): void
    {
        $event = $this->event(['recurrence' => 'weekly']);
        $otherRoster = $this->roster($this->event());
        $otherSlot = $this->slot($otherRoster, ['starts_at' => '2026-10-04 18:00:00', 'ends_at' => '2026-10-04 19:00:00']);
        $payload = $this->configuration();
        $payload['shifts'][0]['id'] = $otherSlot->shift_id;
        $this->actingAs($this->member($event->owner));
        $auditCount = AuditLog::count();

        $this->put(route('events.roster.update', $event), $payload)->assertSessionHasErrors('shifts.0.id');
        unset($payload['shifts'][0]['id']);
        $payload['shifts'][0]['slots'][0]['id'] = $otherSlot->id;
        $this->put(route('events.roster.update', $event), $payload)->assertSessionHasErrors('shifts.0.slots.0.id');

        $this->assertDatabaseCount('event_rosters', 1);
        $this->assertDatabaseHas('roster_slots', ['id' => $otherSlot->id, 'start_time' => '18:00']);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_positions_with_submitted_interest_cannot_be_removed_renamed_or_switched_to_slots(): void
    {
        $event = $this->event();
        $roster = $this->roster($event, ['mode' => 'open_interest']);
        $position = RosterPosition::factory()->create(['roster_id' => $roster->id, 'callsign' => 'EKCH_A_TWR']);
        $controller = $this->member($event->owner, RoleName::Controller);
        $interest = RosterInterest::factory()->create(['roster_id' => $roster->id, 'user_cid' => $controller->cid, ...$this->interestPayload([$position->id])]);
        $this->actingAs($this->member($event->owner));
        $auditCount = AuditLog::count();

        $this->put(route('events.roster.update', $event), ['occurrence_date' => '2026-10-04', 'mode' => 'open_interest', 'is_open' => true, 'shifts' => [], 'positions' => [['id' => $position->id, 'callsign' => 'EKCH_APP']]])->assertSessionHasErrors('positions.0.callsign');
        $this->put(route('events.roster.update', $event), ['occurrence_date' => '2026-10-04', 'mode' => 'open_interest', 'is_open' => false, 'shifts' => [], 'positions' => []])->assertSessionHasErrors('positions');
        $this->put(route('events.roster.update', $event), $this->configuration())->assertSessionHasErrors('mode');

        $this->assertDatabaseHas('roster_positions', ['id' => $position->id, 'callsign' => 'EKCH_A_TWR']);
        $this->assertSame([$position->id], $interest->fresh()->position_ids);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_coordinator_configures_open_interest_positions_and_duplicate_callsigns_are_rejected(): void
    {
        $event = $this->event();
        $this->actingAs($this->member($event->owner));

        $this->put(route('events.roster.update', $event), ['occurrence_date' => '2026-10-04', 'mode' => 'open_interest', 'is_open' => true, 'shifts' => [], 'positions' => [['callsign' => ' ekch_a_twr '], ['callsign' => 'EKCH_APP']]])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('event_rosters', ['event_id' => $event->id, 'mode' => 'open_interest']);
        $this->assertDatabaseHas('roster_positions', ['callsign' => 'EKCH_A_TWR']);
        $this->assertDatabaseCount('roster_positions', 2);
        $auditCount = AuditLog::count();

        $this->put(route('events.roster.update', $event), ['occurrence_date' => '2026-10-04', 'mode' => 'open_interest', 'is_open' => true, 'shifts' => [], 'positions' => [['callsign' => 'EKCH_APP'], ['callsign' => 'ekch_app']]])->assertSessionHasErrors('positions.1.callsign');

        $this->assertDatabaseCount('roster_positions', 2);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_controller_submits_updates_and_withdraws_own_interest_with_selected_positions_and_availability(): void
    {
        $event = $this->event();
        $roster = $this->roster($event, ['mode' => 'open_interest']);
        $position = RosterPosition::factory()->create(['roster_id' => $roster->id, 'callsign' => 'EKCH_A_TWR']);
        $second = RosterPosition::factory()->create(['roster_id' => $roster->id, 'callsign' => 'EKCH_APP']);
        $controller = $this->member($event->owner, RoleName::Controller);
        $other = User::factory()->create();

        $this->actingAs($controller)->put(route('roster.interest.update', $roster), [...$this->interestPayload([$position->id]), 'user_cid' => $other->cid])->assertSessionHasNoErrors();

        $interest = RosterInterest::firstOrFail();
        $this->assertSame($controller->cid, $interest->user_cid);
        $this->assertSame([$position->id], $interest->position_ids);
        $this->assertDatabaseHas('audit_logs', ['subject_type' => 'roster', 'subject_id' => $roster->id, 'event' => 'interest_submitted', 'actor_cid' => $controller->cid]);

        $this->put(route('roster.interest.update', $roster), $this->interestPayload([$position->id, $second->id], '2026-10-04T19:00', '2026-10-04T21:00'))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('roster_interests', 1);
        $this->assertSame([$position->id, $second->id], $interest->fresh()->position_ids);
        $this->assertSame('2026-10-04T19:00', substr($interest->fresh()->availability[0]['starts_at'], 0, 16));

        $this->delete(route('roster.interest.destroy', $roster), ['occurrence_date' => '2026-10-04', 'user_cid' => $other->cid])->assertSessionHasNoErrors();

        $this->assertModelMissing($interest);
        $this->assertDatabaseHas('audit_logs', ['subject_type' => 'roster', 'subject_id' => $roster->id, 'event' => 'interest_withdrawn', 'actor_cid' => $controller->cid]);
    }

    public function test_interest_cannot_select_positions_from_another_roster_and_rejection_preserves_saved_interest(): void
    {
        $event = $this->event();
        $roster = $this->roster($event, ['mode' => 'open_interest']);
        $position = RosterPosition::factory()->create(['roster_id' => $roster->id]);
        $otherPosition = RosterPosition::factory()->create();
        $controller = $this->member($event->owner, RoleName::Controller);
        $interest = RosterInterest::factory()->create(['roster_id' => $roster->id, 'user_cid' => $controller->cid, ...$this->interestPayload([$position->id])]);
        $auditCount = AuditLog::count();

        $this->actingAs($controller)->put(route('roster.interest.update', $roster), $this->interestPayload([$otherPosition->id]))->assertSessionHasErrors('position_ids');

        $this->assertSame([$position->id], $interest->fresh()->position_ids);
        $this->assertDatabaseCount('roster_interests', 1);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    #[TestWith(['2026-10-04T17:59', '2026-10-04T19:00', 'availability.0.starts_at'])]
    #[TestWith(['2026-10-04T18:00', '2026-10-04T21:01', 'availability.0.starts_at'])]
    #[TestWith(['2026-10-04T19:00', '2026-10-04T18:00', 'availability.0.ends_at'])]
    public function test_invalid_availability_is_rejected_without_interest_or_audit(string $start, string $end, string $error): void
    {
        $event = $this->event();
        $roster = $this->roster($event, ['mode' => 'open_interest']);
        $position = RosterPosition::factory()->create(['roster_id' => $roster->id]);
        $this->actingAs($this->member($event->owner, RoleName::Controller));
        $auditCount = AuditLog::count();

        $this->put(route('roster.interest.update', $roster), $this->interestPayload([$position->id], $start, $end))->assertSessionHasErrors($error);

        $this->assertDatabaseCount('roster_interests', 0);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_interest_requires_positions_and_nonoverlapping_availability(): void
    {
        $event = $this->event();
        $roster = $this->roster($event, ['mode' => 'open_interest']);
        $position = RosterPosition::factory()->create(['roster_id' => $roster->id]);
        $this->actingAs($this->member($event->owner, RoleName::Controller));
        $payload = $this->interestPayload([$position->id]);
        $payload['availability'][] = ['starts_at' => '2026-10-04T18:55', 'ends_at' => '2026-10-04T20:00'];
        $auditCount = AuditLog::count();

        $this->put(route('roster.interest.update', $roster), ['position_ids' => [], 'availability' => []])->assertSessionHasErrors(['position_ids', 'availability']);
        $this->put(route('roster.interest.update', $roster), $payload)->assertSessionHasErrors('availability.1.starts_at');

        $this->assertDatabaseCount('roster_interests', 0);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_controllers_only_see_their_own_interest_and_coordinators_see_all_submissions(): void
    {
        $event = $this->event();
        $roster = $this->roster($event, ['mode' => 'open_interest']);
        $position = RosterPosition::factory()->create(['roster_id' => $roster->id]);
        $controller = $this->member($event->owner, RoleName::Controller);
        $other = $this->member($event->owner, RoleName::Controller);
        RosterInterest::factory()->create(['roster_id' => $roster->id, 'user_cid' => $controller->cid, ...$this->interestPayload([$position->id])]);
        RosterInterest::factory()->create(['roster_id' => $roster->id, 'user_cid' => $other->cid, ...$this->interestPayload([$position->id])]);

        $this->actingAs($controller)->get(route('events.roster.show', [$event, '2026-10-04']))->assertInertia(fn (Assert $page) => $page
            ->has('roster.interests', 1)->where('roster.interests.0.user.cid', $controller->cid));
        $this->actingAs($this->member($event->owner))->get(route('events.roster.show', [$event, '2026-10-04']))->assertInertia(fn (Assert $page) => $page->has('roster.interests', 2));

        $this->actingAs($controller)->delete(route('roster.interest.destroy', $roster), ['occurrence_date' => '2026-10-04', 'user_cid' => $other->cid])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('roster_interests', ['roster_id' => $roster->id, 'user_cid' => $other->cid]);
        $this->assertDatabaseMissing('roster_interests', ['roster_id' => $roster->id, 'user_cid' => $controller->cid]);
    }

    #[TestWith(['closed'])]
    #[TestWith(['cancelled'])]
    #[TestWith(['occurrence_cancelled'])]
    #[TestWith(['past'])]
    public function test_unavailable_rosters_reject_interest_changes_but_allow_own_withdrawal(string $state): void
    {
        $event = $this->event();
        $roster = $this->roster($event, ['mode' => 'open_interest']);
        $position = RosterPosition::factory()->create(['roster_id' => $roster->id]);
        $controller = $this->member($event->owner, RoleName::Controller);
        $interest = RosterInterest::factory()->create(['roster_id' => $roster->id, 'user_cid' => $controller->cid, ...$this->interestPayload([$position->id])]);
        $this->makeUnavailable($event, $roster, $state);
        $auditCount = AuditLog::count();

        $this->actingAs($controller)->put(route('roster.interest.update', $roster), $this->interestPayload([$position->id], '2026-10-04T19:00', '2026-10-04T20:00'))->assertForbidden();

        $this->assertSame($this->interestPayload([$position->id])['availability'], $interest->fresh()->availability);
        $this->assertDatabaseCount('audit_logs', $auditCount);

        $this->delete(route('roster.interest.destroy', $roster), ['occurrence_date' => '2026-10-04'])->assertSessionHasNoErrors();

        $this->assertModelMissing($interest);
    }

    public function test_coordinator_cannot_open_an_empty_roster_or_mix_modes(): void
    {
        $event = $this->event();
        $this->actingAs($this->member($event->owner));
        $auditCount = AuditLog::count();

        $this->put(route('events.roster.update', $event), ['occurrence_date' => '2026-10-04', 'mode' => 'pre_slotted', 'is_open' => true, 'shifts' => [], 'positions' => []])->assertInvalid(['is_open' => 'Add at least one position before opening the roster.']);
        $this->put(route('events.roster.update', $event), [...$this->configuration(), 'mode' => 'open_interest'])->assertInvalid(['shifts' => 'Open interest rosters use a list of positions.']);
        $this->put(route('events.roster.update', $event), [...$this->configuration(), 'positions' => [['callsign' => 'EKCH_APP']]])->assertInvalid(['positions' => 'Pre-slotted rosters use shifts and slots.']);

        $this->assertDatabaseCount('event_rosters', 0);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_coordinator_can_replace_an_unused_roster_with_the_other_mode(): void
    {
        $event = $this->event();
        $roster = $this->roster($event);
        $this->slot($roster);
        $coordinator = $this->member($event->owner);

        $this->actingAs($coordinator)->put(route('events.roster.update', $event), ['occurrence_date' => '2026-10-04', 'mode' => 'open_interest', 'is_open' => true, 'shifts' => [], 'positions' => [['callsign' => 'EKCH_APP']]])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('event_rosters', ['id' => $roster->id, 'mode' => 'open_interest']);
        $this->assertDatabaseCount('roster_shifts', 0);
        $this->assertDatabaseCount('roster_slots', 0);
        $this->assertDatabaseHas('roster_positions', ['roster_id' => $roster->id, 'callsign' => 'EKCH_APP']);
    }

    public function test_interest_cannot_be_submitted_to_a_pre_slotted_roster(): void
    {
        $event = $this->event();
        $roster = $this->roster($event);
        $controller = $this->member($event->owner, RoleName::Controller);
        $auditCount = AuditLog::count();

        $this->actingAs($controller)->put(route('roster.interest.update', $roster), $this->interestPayload([1]))->assertInvalid(['interest' => 'This roster does not accept open interest.']);

        $this->assertDatabaseCount('roster_interests', 0);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_a_roster_is_configured_once_and_automatically_available_on_later_occurrences(): void
    {
        $event = $this->event(['recurrence' => 'weekly']);
        $this->actingAs($this->member($event->owner));
        $this->put(route('events.roster.update', $event), $this->configuration())->assertSessionHasNoErrors();
        $roster = EventRoster::firstOrFail();
        $slot = RosterSlot::firstOrFail();

        $this->get(route('events.roster.show', [$event, '2026-10-11']))->assertInertia(fn (Assert $page) => $page
            ->where('roster.id', $roster->id)->where('roster.mode', 'pre_slotted')
            ->where('occurrence.date', '2026-10-11')->where('roster.shifts.0.slots.0.id', $slot->id)
            ->where('roster.shifts.0.slots.0.starts_at', '2026-10-11T18:00')
            ->where('roster.shifts.0.slots.0.booking', null));

        $this->assertDatabaseCount('event_rosters', 1);
        $this->assertDatabaseCount('roster_slots', 1);
        $this->assertDatabaseCount('roster_bookings', 0);
    }

    public function test_nonexistent_occurrences_cannot_have_a_roster(): void
    {
        $event = $this->event(['recurrence' => 'weekly']);
        $this->actingAs($this->member($event->owner));
        $auditCount = AuditLog::count();

        $this->get(route('events.roster.show', [$event, '2026-10-05']))->assertNotFound();
        $this->put(route('events.roster.update', $event), [...$this->configuration(), 'occurrence_date' => '2026-10-05'])->assertSessionHasErrors('roster');

        $this->assertDatabaseCount('event_rosters', 0);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    /** @param array<string, mixed> $attributes */
    private function event(array $attributes = []): Event
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-09T12:00:00Z'));

        return Event::factory()->rostered()->create($attributes);
    }

    /** @param array<string, mixed> $attributes */
    private function roster(Event $event, array $attributes = []): EventRoster
    {
        return EventRoster::factory()->create(['event_id' => $event->id, 'mode' => 'pre_slotted', 'is_open' => true, 'opened_at' => now(), ...$attributes]);
    }

    /** @param array<string, mixed> $attributes */
    private function slot(EventRoster $roster, array $attributes = []): RosterSlot
    {
        $shift = RosterShift::factory()->create(['roster_id' => $roster->id]);
        $start = CarbonImmutable::parse($attributes['starts_at'] ?? '2026-10-04 18:00:00', 'UTC');
        $end = CarbonImmutable::parse($attributes['ends_at'] ?? '2026-10-04 19:00:00', 'UTC');
        $localStart = $start->setTimezone($roster->event->timezone);
        $localEnd = $end->setTimezone($roster->event->timezone);
        $occurrenceDate = $attributes['booking_date'] ?? '2026-10-04';
        $controllerCid = $attributes['booking_user_cid'] ?? null;
        unset($attributes['starts_at'], $attributes['ends_at'], $attributes['booking_user_cid'], $attributes['booking_date']);
        $slot = RosterSlot::factory()->create([
            'shift_id' => $shift->id, 'callsign' => 'EKCH_A_TWR',
            'start_day_offset' => (int) CarbonImmutable::parse($occurrenceDate)->diffInDays(CarbonImmutable::parse($localStart->toDateString())),
            'start_time' => $localStart->format('H:i'),
            'end_day_offset' => (int) CarbonImmutable::parse($occurrenceDate)->diffInDays(CarbonImmutable::parse($localEnd->toDateString())),
            'end_time' => $localEnd->format('H:i'),
            ...$attributes,
        ]);
        if ($controllerCid !== null) {
            RosterBooking::factory()->create([
                'roster_id' => $roster->id, 'slot_id' => $slot->id, 'user_cid' => $controllerCid,
                'occurrence_date' => $occurrenceDate, 'callsign' => $slot->callsign, 'shift_name' => $shift->name,
                'starts_at' => $start, 'ends_at' => $end,
            ]);
        }

        return $slot;
    }

    /** @return array{occurrence_date: string, mode: string, is_open: bool, positions: array{}, shifts: list<array{name: string, slots: list<array{callsign: string, starts_at: string, ends_at: string}>}>} */
    private function configuration(): array
    {
        return ['occurrence_date' => '2026-10-04', 'mode' => 'pre_slotted', 'is_open' => true, 'positions' => [], 'shifts' => [['name' => 'Early', 'slots' => [['callsign' => 'EKCH_A_TWR', 'starts_at' => '2026-10-04T18:00', 'ends_at' => '2026-10-04T19:00']]]]];
    }

    /**
     * @param  list<int>  $positions
     * @return array{occurrence_date: string, position_ids: list<int>, availability: list<array{starts_at: string, ends_at: string}>}
     */
    private function interestPayload(array $positions, string $start = '2026-10-04T18:00', string $end = '2026-10-04T19:00'): array
    {
        return ['occurrence_date' => '2026-10-04', 'position_ids' => $positions, 'availability' => [['starts_at' => $start, 'ends_at' => $end]]];
    }

    private function makeUnavailable(Event $event, EventRoster $roster, string $state): void
    {
        match ($state) {
            'closed' => $roster->update(['is_open' => false]),
            'cancelled' => $event->update(['status' => 'cancelled']),
            'occurrence_cancelled' => EventCancellation::factory()->for($event)->create(['occurrence_date' => '2026-10-04']),
            'past' => $this->travelTo(CarbonImmutable::parse('2026-10-05T00:00:00Z')),
        };
    }

    private function member(Team $fir, RoleName $role = RoleName::EventCoordinator): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        if ($role !== RoleName::Pilot) {
            app(UpdateRoleAssignments::class)->grant($user, $role, $role->isGlobal() ? null : $fir);
        }

        return $user;
    }
}
