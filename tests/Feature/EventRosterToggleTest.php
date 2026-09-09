<?php

namespace Tests\Feature;

use App\Actions\Authorization\UpdateRoleAssignments;
use App\Models\Airport;
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
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class EventRosterToggleTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_coordinator_creates_an_event_with_an_explicit_roster_choice(bool $enabled): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $fir = Team::factory()->create();
        $coordinator = $this->member($fir);
        $payload = $this->payload(Event::factory()->make(['owner_team_id' => $fir->id]), ['roster_enabled' => $enabled]);

        $this->actingAs($coordinator)->post(route('events.store'), $payload)->assertSessionHasNoErrors();

        $event = Event::firstOrFail();
        $this->assertSame($enabled, $event->roster_enabled);
        $this->assertDatabaseCount('event_rosters', 0);
        $audit = AuditLog::where('subject_type', 'event')->where('subject_id', $event->id)->firstOrFail();
        $this->assertSame($enabled, $audit->new_values['roster_enabled']);
        $this->get(route('events.edit', $event))->assertInertia(fn (Assert $page) => $page
            ->where('event.roster_enabled', $enabled)->where('event.schedule_locked', false));
    }

    public function test_omitting_the_roster_choice_defaults_off_on_create_and_preserves_it_on_update(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $fir = Team::factory()->create();
        $coordinator = $this->member($fir);
        $payload = $this->payload(Event::factory()->make(['owner_team_id' => $fir->id]));
        unset($payload['roster_enabled']);

        $this->actingAs($coordinator)->post(route('events.store'), $payload)->assertSessionHasNoErrors();

        $created = Event::firstOrFail();
        $this->assertFalse($created->roster_enabled);

        $enabled = Event::factory()->rostered()->create(['owner_team_id' => $fir->id]);
        $this->put(route('events.update', $enabled), [...$payload, 'title' => 'Updated description'])->assertSessionHasNoErrors();

        $this->assertTrue($enabled->fresh()->roster_enabled);
        $this->assertSame('Updated description', $enabled->fresh()->title);
    }

    public function test_enabling_a_roster_without_a_template_does_not_lock_the_schedule(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->rostered()->weekly()->create();
        $coordinator = $this->member($event->owner);
        $payload = $this->payload($event, ['local_start' => '2026-10-11T19:00', 'local_end' => '2026-10-11T22:00', 'recurrence_interval' => 2]);

        $this->actingAs($coordinator)->put(route('events.update', $event), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('events', ['id' => $event->id, 'roster_enabled' => true, 'local_start' => '2026-10-11T19:00', 'recurrence_interval' => 2]);
        $this->assertDatabaseCount('event_rosters', 0);
        $this->get(route('events.edit', $event))->assertInertia(fn (Assert $page) => $page->where('event.schedule_locked', false));
    }

    public function test_disabling_an_empty_roster_unlocks_schedule_changes_in_the_same_request_and_preserves_its_closed_template(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->rostered()->weekly()->create();
        $roster = EventRoster::factory()->for($event)->open()->create();
        $slot = RosterSlot::factory()->create(['shift_id' => RosterShift::factory()->create(['roster_id' => $roster->id, 'name' => 'Early'])->id, 'start_time' => '18:00', 'end_time' => '19:00']);
        $coordinator = $this->member($event->owner);
        $payload = $this->payload($event, ['roster_enabled' => false, 'local_start' => '2026-10-11T19:00', 'local_end' => '2026-10-11T22:00', 'recurrence_interval' => 2]);

        $this->actingAs($coordinator)->put(route('events.update', $event), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('events', ['id' => $event->id, 'roster_enabled' => false, 'local_start' => '2026-10-11T19:00', 'recurrence_interval' => 2]);
        $this->assertDatabaseHas('event_rosters', ['id' => $roster->id, 'is_open' => false]);
        $this->assertDatabaseHas('roster_slots', ['id' => $slot->id, 'start_time' => '18:00', 'end_time' => '19:00']);
        $eventAudit = AuditLog::where('subject_type', 'event')->where('subject_id', $event->id)->latest('id')->firstOrFail();
        $this->assertSame(true, $eventAudit->old_values['roster_enabled']);
        $this->assertSame(false, $eventAudit->new_values['roster_enabled']);
        $this->assertDatabaseHas('audit_logs', ['subject_type' => 'roster', 'subject_id' => $roster->id, 'event' => 'updated', 'actor_cid' => $coordinator->cid]);
        $this->get(route('events.edit', $event))->assertInertia(fn (Assert $page) => $page->where('event.schedule_locked', false));

        $this->put(route('events.update', $event), [...$payload, 'roster_enabled' => true])->assertSessionHasNoErrors();

        $this->assertTrue($event->fresh()->roster_enabled);
        $this->assertFalse($roster->fresh()->is_open);
        $this->assertModelExists($slot);
        $this->get(route('events.edit', $event))->assertInertia(fn (Assert $page) => $page->where('event.schedule_locked', true));
    }

    #[TestWith(['booking', '2026-10-04'])]
    #[TestWith(['booking', '2026-10-11'])]
    #[TestWith(['interest', '2026-10-04'])]
    #[TestWith(['interest', '2026-10-11'])]
    public function test_any_dated_commitment_prevents_disabling_and_rolls_back_other_event_edits(string $kind, string $date): void
    {
        $this->travelTo('2026-10-05 12:00:00');
        $event = Event::factory()->rostered()->weekly()->create();
        $roster = EventRoster::factory()->for($event)->open()->create(['mode' => $kind === 'interest' ? 'open_interest' : 'pre_slotted']);
        if ($kind === 'booking') {
            $commitment = RosterBooking::factory()->create(['roster_id' => $roster->id, 'slot_id' => RosterSlot::factory()->create(['shift_id' => RosterShift::factory()->create(['roster_id' => $roster->id, 'name' => 'Early'])->id])->id, 'occurrence_date' => $date]);
        } else {
            $commitment = RosterInterest::factory()->create(['roster_id' => $roster->id, 'occurrence_date' => $date]);
        }
        $coordinator = $this->member($event->owner);
        $payload = $this->payload($event, ['roster_enabled' => false, 'title' => 'Must not persist', 'local_start' => '2026-10-11T19:00', 'local_end' => '2026-10-11T22:00']);
        $auditCount = AuditLog::count();

        $this->actingAs($coordinator)->put(route('events.update', $event), $payload)->assertSessionHasErrors('roster_enabled');

        $this->assertDatabaseHas('events', ['id' => $event->id, 'roster_enabled' => true, 'title' => $event->title, 'local_start' => '2026-10-04T18:00']);
        $this->assertTrue($roster->fresh()->is_open);
        $this->assertModelExists($commitment);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_disabling_does_not_unlock_dates_that_have_individual_cancellations(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->rostered()->weekly()->create();
        $roster = EventRoster::factory()->for($event)->open()->create();
        $cancellation = EventCancellation::factory()->for($event)->create(['occurrence_date' => '2026-10-11']);
        $coordinator = $this->member($event->owner);
        $payload = $this->payload($event, ['roster_enabled' => false]);
        $auditCount = AuditLog::count();

        $this->actingAs($coordinator)->put(route('events.update', $event), [...$payload, 'local_start' => '2026-10-04T19:00'])->assertSessionHasErrors('recurrence');

        $this->assertTrue($event->fresh()->roster_enabled);
        $this->assertTrue($roster->fresh()->is_open);
        $this->assertDatabaseCount('audit_logs', $auditCount);

        $this->put(route('events.update', $event), $payload)->assertSessionHasNoErrors();

        $this->assertFalse($event->fresh()->roster_enabled);
        $this->assertModelExists($cancellation);
        $this->get(route('events.edit', $event))->assertInertia(fn (Assert $page) => $page->where('event.schedule_locked', true));
    }

    public function test_accepted_collaborator_coordinator_can_toggle_but_staff_and_unrelated_coordinators_cannot(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->create();
        $collaboration = EventCollaboration::factory()->for($event)->accepted()->create();
        $coordinator = $this->member($collaboration->team);
        $staff = $this->member($event->owner, RoleName::VaccStaff);
        $outsider = $this->member(Team::factory()->create());
        $payload = $this->payload($event, ['roster_enabled' => true]);
        $auditCount = AuditLog::count();

        $this->actingAs($staff)->put(route('events.update', $event), $payload)->assertForbidden();
        $this->actingAs($outsider)->put(route('events.update', $event), $payload)->assertForbidden();

        $this->assertFalse($event->fresh()->roster_enabled);
        $this->assertDatabaseCount('audit_logs', $auditCount);

        $this->actingAs($coordinator)->put(route('events.update', $event), $payload)->assertSessionHasNoErrors();

        $this->assertTrue($event->fresh()->roster_enabled);
        $this->assertDatabaseHas('audit_logs', ['subject_type' => 'event', 'subject_id' => $event->id, 'event' => 'updated', 'actor_cid' => $coordinator->cid]);
    }

    public function test_disabled_rosters_are_hidden_and_cannot_be_configured_or_booked_through_direct_endpoints(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->create();
        $roster = EventRoster::factory()->for($event)->open()->create();
        $slot = RosterSlot::factory()->create(['shift_id' => RosterShift::factory()->create(['roster_id' => $roster->id, 'name' => 'Early'])->id]);
        $coordinator = $this->member($event->owner);
        $controller = $this->member($event->owner, RoleName::Controller);
        $administrator = $this->member($event->owner, RoleName::Administrator);
        $auditCount = AuditLog::count();

        $this->actingAs($coordinator)->get(route('events.roster.show', $event))->assertNotFound();
        $this->put(route('events.roster.update', $event), ['occurrence_date' => '2026-10-04', 'mode' => 'pre_slotted', 'is_open' => false, 'shifts' => [], 'positions' => []])->assertInvalid(['roster' => 'Enable the roster in the event settings before configuring it.']);
        $this->get(route('rosters.index'))->assertInertia(fn (Assert $page) => $page->has('rosters.data', 0));
        $this->actingAs($controller)->get(route('events.roster.show', [$event, '2026-10-04']))->assertNotFound();
        $this->post(route('roster.bookings.store', [$roster, $slot]), ['occurrence_date' => '2026-10-04'])->assertForbidden();
        $this->get(route('rosters.index'))->assertInertia(fn (Assert $page) => $page->has('rosters.data', 0));

        $this->actingAs($administrator)->get(route('events.roster.show', $event))->assertNotFound();
        $this->get(route('rosters.index'))->assertInertia(fn (Assert $page) => $page->has('rosters.data', 0));

        $this->assertFalse($controller->can('participate', [$roster, '2026-10-04']));
        $this->assertDatabaseCount('roster_bookings', 0);
        $this->assertModelExists($slot);
        $this->assertTrue($roster->fresh()->is_open);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_disabled_open_interest_rosters_reject_direct_submissions(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->create();
        $roster = EventRoster::factory()->for($event)->openInterest()->open()->create();
        $position = RosterPosition::factory()->create(['roster_id' => $roster->id]);
        $controller = $this->member($event->owner, RoleName::Controller);
        $auditCount = AuditLog::count();

        $this->actingAs($controller)->put(route('roster.interest.update', $roster), ['occurrence_date' => '2026-10-04', 'position_ids' => [$position->id], 'availability' => [['starts_at' => '2026-10-04T18:00', 'ends_at' => '2026-10-04T19:00']]])->assertForbidden();

        $this->assertDatabaseCount('roster_interests', 0);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_invalid_roster_toggle_values_are_rejected_without_creating_an_event(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $fir = Team::factory()->create();
        $coordinator = $this->member($fir);
        $payload = $this->payload(Event::factory()->make(['owner_team_id' => $fir->id]), ['roster_enabled' => 'sometimes']);
        $auditCount = AuditLog::count();

        $this->actingAs($coordinator)->post(route('events.store'), $payload)->assertSessionHasErrors('roster_enabled');

        $this->assertDatabaseCount('events', 0);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_migration_enables_existing_roster_events_and_keeps_events_without_rosters_disabled(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $withRoster = Event::factory()->create();
        $withoutRoster = Event::factory()->create();
        $migration = require database_path('migrations/2026_09_09_121340_add_roster_enabled_to_events_table.php');
        $migration->down();
        $rosterId = DB::table('event_rosters')->insertGetId(['event_id' => $withRoster->id, 'mode' => 'pre_slotted', 'is_open' => false, 'opened_at' => null]);

        $migration->up();

        $this->assertDatabaseHas('events', ['id' => $withRoster->id, 'roster_enabled' => true]);
        $this->assertDatabaseHas('events', ['id' => $withoutRoster->id, 'roster_enabled' => false]);
        $this->assertDatabaseHas('event_rosters', ['id' => $rosterId, 'event_id' => $withRoster->id, 'is_open' => false, 'opened_at' => null]);
    }

    /** @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    private function payload(Event $event, array $changes = []): array
    {
        return [
            'owner_team_id' => $event->owner_team_id, 'title' => $event->title, 'short_description' => $event->short_description,
            'description' => $event->description, 'airport_ids' => [Airport::factory()->create()->id], 'timezone' => $event->timezone,
            'local_start' => $event->local_start, 'local_end' => $event->local_end, 'recurrence' => $event->recurrence,
            'recurrence_interval' => $event->recurrence_interval, 'monthly_week' => $event->monthly_week,
            'recurrence_until' => $event->recurrence_until?->toDateString(), 'roster_enabled' => $event->roster_enabled,
            ...$changes,
        ];
    }

    private function member(Team $fir, RoleName $role = RoleName::EventCoordinator): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        app(UpdateRoleAssignments::class)->grant($user, $role, $role->isGlobal() ? null : $fir);

        return $user;
    }
}
