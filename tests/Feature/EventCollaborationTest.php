<?php

namespace Tests\Feature;

use App\Actions\Authorization\UpdateRoleAssignments;
use App\Models\Airport;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventCollaboration;
use App\Models\Team;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventCollaborationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_invites_an_fir_and_only_its_coordinator_can_accept(): void
    {
        $event = Event::factory()->create();
        $owner = $this->member($event->owner);
        $partner = Team::factory()->create();
        $coordinator = $this->member($partner);
        $staff = $this->member($partner, RoleName::VaccStaff);

        $this->actingAs($owner)->post(route('events.collaborations.store', $event), ['team_id' => $partner->id])->assertSessionHasNoErrors();
        $invitation = EventCollaboration::firstOrFail();
        $this->assertNull($invitation->accepted_at);

        $this->actingAs($coordinator)->get(route('events.show', $event))->assertNotFound();
        $this->get(route('events.index'))->assertInertia(fn (Assert $page) => $page->has('events.data', 0)
            ->has('invitations', 1)->where('invitations.0.id', $invitation->id)->missing('invitations.0.title'));

        $this->actingAs($staff)->patch(route('events.collaborations.update', [$event, $invitation]))->assertForbidden();
        $this->assertNull($invitation->fresh()->accepted_at);

        $this->actingAs($coordinator)->patch(route('events.collaborations.update', [$event, $invitation]))->assertRedirectToRoute('events.show', $event);
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page->where('can.edit', true)->where('can.manage_owner', false));
        $this->actingAs($staff)->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page->where('can.edit', false)->where('can.manage_owner', false));
        $this->assertSame(2, AuditLog::where('subject_type', 'event')->count());
    }

    public function test_collaborator_can_edit_and_cancel_one_occurrence_but_cannot_cancel_the_series_or_invite(): void
    {
        $event = Event::factory()->weekly()->create();
        $collaboration = EventCollaboration::factory()->for($event)->accepted()->create();
        $coordinator = $this->member($collaboration->team);
        $airport = Airport::factory()->create();

        $this->actingAs($coordinator)->put(route('events.update', $event), [
            'owner_team_id' => $event->owner_team_id, 'title' => 'Coordinated event', 'short_description' => $event->short_description,
            'description' => $event->description, 'airport_ids' => [$airport->id], 'timezone' => $event->timezone,
            'local_start' => $event->local_start, 'local_end' => $event->local_end,
            'recurrence' => 'weekly', 'recurrence_interval' => 1,
        ])->assertSessionHasNoErrors()->assertRedirectToRoute('events.show', $event);
        $this->assertSame('Coordinated event', $event->fresh()->title);

        $this->post(route('events.cancellations.store', $event), ['occurrence_date' => '2026-10-11', 'reason' => 'No staffing'])->assertSessionHasNoErrors();
        $this->post(route('events.cancellations.store', $event), ['reason' => 'Entire series'])->assertForbidden();
        $this->post(route('events.collaborations.store', $event), ['team_id' => Team::factory()->create()->id])->assertForbidden();
        $this->delete(route('events.collaborations.destroy', [$event, $collaboration]))->assertForbidden();

        $this->assertSame('draft', $event->fresh()->status);
        $this->assertDatabaseHas('event_cancellations', ['event_id' => $event->id, 'occurrence_date' => '2026-10-11']);
        $this->assertDatabaseCount('event_collaborations', 1);
    }

    public function test_owner_can_revoke_access_and_nested_ids_cannot_cross_event_boundaries(): void
    {
        $event = Event::factory()->create();
        $collaboration = EventCollaboration::factory()->for($event)->accepted()->create();
        $coordinator = $this->member($collaboration->team);
        $otherEvent = Event::factory()->create();

        $this->actingAs($coordinator)->patch(route('events.collaborations.update', [$otherEvent, $collaboration]))->assertNotFound();
        $this->actingAs($this->member($event->owner))->delete(route('events.collaborations.destroy', [$otherEvent, $collaboration]))->assertNotFound();
        $this->delete(route('events.collaborations.destroy', [$event, $collaboration]))->assertSessionHasNoErrors();

        $this->assertModelMissing($collaboration);
        $this->actingAs($coordinator)->get(route('events.show', $event))->assertNotFound();
        $this->assertFalse($coordinator->can('update', $event->fresh()));
    }

    public function test_invitation_creation_is_idempotent_and_does_not_allow_the_owner_fir(): void
    {
        $event = Event::factory()->create();
        $partner = Team::factory()->create();

        $this->actingAs($this->member($event->owner))->post(route('events.collaborations.store', $event), ['team_id' => $partner->id])->assertSessionHasNoErrors();
        $this->post(route('events.collaborations.store', $event), ['team_id' => $partner->id])->assertSessionHasNoErrors();
        $this->post(route('events.collaborations.store', $event), ['team_id' => $event->owner_team_id])->assertSessionHasErrors('team_id');

        $this->assertDatabaseCount('event_collaborations', 1);
        $this->assertSame(1, AuditLog::where('subject_type', 'event')->count());
    }

    private function member(Team $fir, RoleName $role = RoleName::EventCoordinator): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        app(UpdateRoleAssignments::class)->grant($user, $role, $fir);

        return $user;
    }
}
