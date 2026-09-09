<?php

namespace Tests\Feature;

use App\Actions\Authorization\UpdateRoleAssignments;
use App\Models\Airport;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventCancellation;
use App\Models\EventCollaboration;
use App\Models\Team;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class EventPublicationTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith([RoleName::EventCoordinator, true])]
    #[TestWith([RoleName::Administrator, true])]
    #[TestWith([RoleName::VaccStaff, false])]
    #[TestWith([RoleName::Controller, false])]
    #[TestWith([RoleName::Pilot, false])]
    public function test_only_owner_coordinators_and_administrators_have_publication_permission(RoleName $role, bool $allowed): void
    {
        $event = Event::factory()->create();
        $user = $this->member($event->owner, $role);

        $this->assertSame($allowed, $user->can('publish', $event));
    }

    #[TestWith([RoleName::EventCoordinator])]
    #[TestWith([RoleName::Administrator])]
    public function test_publish_and_unpublish_are_audited_once_and_ignore_client_supplied_state(RoleName $role): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->create();
        $user = $this->member($event->owner, $role);

        $this->actingAs($user)->post(route('events.publication.store', $event), ['published_at' => '2000-01-01T00:00:00Z', 'status' => 'cancelled', 'title' => 'Forged title'])
            ->assertSessionHasNoErrors()->assertRedirectToRoute('events.show', $event);

        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'published', 'published_at' => '2026-09-09 12:00:00', 'title' => $event->title]);
        $published = AuditLog::where('subject_type', 'event')->where('subject_id', $event->id)->sole();
        $this->assertSame($user->cid, $published->actor_cid);
        $this->assertSame('published', $published->event);
        $this->assertSame('draft', $published->old_values['status']);
        $this->assertSame('published', $published->new_values['status']);
        $this->assertArrayHasKey('published_at', $published->new_values);

        $this->travelTo('2026-09-09 13:00:00');
        $this->post(route('events.publication.store', $event))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('events', ['id' => $event->id, 'published_at' => '2026-09-09 12:00:00']);
        $this->assertSame(1, AuditLog::where('subject_type', 'event')->where('subject_id', $event->id)->count());

        $this->delete(route('events.publication.destroy', $event))->assertSessionHasNoErrors()->assertRedirectToRoute('events.show', $event);
        $this->delete(route('events.publication.destroy', $event))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'draft', 'published_at' => null]);
        $this->assertSame(2, AuditLog::where('subject_type', 'event')->where('subject_id', $event->id)->count());
        $unpublished = AuditLog::where('subject_type', 'event')->where('subject_id', $event->id)->latest('id')->firstOrFail();
        $this->assertSame('unpublished', $unpublished->event);
        $this->assertSame('published', $unpublished->old_values['status']);
        $this->assertSame(['status' => 'draft', 'published_at' => null], $unpublished->new_values);
        $administrator = $role === RoleName::Administrator ? $user : $this->member($event->owner, RoleName::Administrator);

        foreach (['published', 'unpublished'] as $action) {
            $this->actingAs($administrator)->get(route('audit-logs.index', ['subject_type' => 'event', 'event' => $action]))->assertInertia(fn (Assert $page) => $page
                ->has('logs.data', 1)->where('logs.data.0.subject_id', $event->id)->where('logs.data.0.event', $action));
        }
    }

    public function test_guests_cannot_publish_or_unpublish_an_event(): void
    {
        $event = Event::factory()->create();
        $published = Event::factory()->published()->create();

        $this->post(route('events.publication.store', $event))->assertRedirectToRoute('login');
        $this->delete(route('events.publication.destroy', $published))->assertRedirectToRoute('login');

        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'draft', 'published_at' => null]);
        $this->assertDatabaseHas('events', ['id' => $published->id, 'status' => 'published']);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_accepted_collaborators_and_read_only_staff_cannot_change_publication(): void
    {
        $event = Event::factory()->create();
        $published = Event::factory()->published()->create(['owner_team_id' => $event->owner_team_id]);
        $collaboration = EventCollaboration::factory()->for($event)->accepted()->create();
        EventCollaboration::factory()->for($published)->accepted()->create(['team_id' => $collaboration->team_id]);
        $collaborator = $this->member($collaboration->team);
        $staff = $this->member($event->owner, RoleName::VaccStaff);
        $outsider = $this->member(Team::factory()->create());
        $auditCount = AuditLog::count();

        $this->assertTrue($collaborator->can('update', $event));
        $this->assertFalse($collaborator->can('publish', $event));
        $this->actingAs($collaborator)->post(route('events.publication.store', $event))->assertForbidden();
        $this->delete(route('events.publication.destroy', $published))->assertForbidden();
        $this->actingAs($staff)->post(route('events.publication.store', $event))->assertForbidden();
        $this->delete(route('events.publication.destroy', $published))->assertForbidden();
        $this->actingAs($outsider)->post(route('events.publication.store', $event))->assertForbidden();

        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'draft', 'published_at' => null]);
        $this->assertDatabaseHas('events', ['id' => $published->id, 'status' => 'published']);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_cancelled_events_cannot_be_published_even_when_their_cancellation_notice_is_public(bool $wasPublished): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->create(['status' => 'cancelled', 'published_at' => $wasPublished ? now() : null, 'cancellation_reason' => 'Insufficient staffing']);
        $user = $this->member($event->owner);
        $auditCount = AuditLog::count();

        $this->actingAs($user)->post(route('events.publication.store', $event))->assertSessionHasErrors('event');

        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'cancelled', 'cancellation_reason' => 'Insufficient staffing']);
        $this->assertSame($wasPublished, $event->fresh()->published_at !== null);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_cancelling_a_public_event_keeps_its_notice_visible_until_it_is_unpublished(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->published()->create();
        $owner = $this->member($event->owner);

        $this->actingAs($owner)->post(route('events.cancellations.store', $event), ['reason' => 'Weather disruption'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'cancelled', 'published_at' => '2026-09-09 12:00:00']);
        $this->actingAsGuest()->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page
            ->where('event.status', 'cancelled')->where('event.cancellation_reason', 'Weather disruption'));

        $this->actingAs($owner)->delete(route('events.publication.destroy', $event))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'cancelled', 'published_at' => null, 'cancellation_reason' => 'Weather disruption']);
        $this->actingAsGuest()->get(route('events.show', $event))->assertNotFound();
    }

    public function test_restoring_a_cancelled_public_series_makes_it_private_and_keeps_individual_cancellations(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->published()->weekly()->create(['status' => 'cancelled', 'cancellation_reason' => 'Series paused', 'cancelled_at' => now()]);
        $cancellation = EventCancellation::factory()->for($event)->create(['occurrence_date' => '2026-10-11']);
        $owner = $this->member($event->owner);

        $this->actingAs($owner)->delete(route('events.cancellations.destroy', $event))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'draft', 'published_at' => null, 'cancelled_at' => null]);
        $this->assertModelExists($cancellation);
        $this->actingAsGuest()->get(route('events.show', $event))->assertNotFound();
        $audit = AuditLog::where('subject_type', 'event')->where('subject_id', $event->id)->sole();
        $this->assertSame('draft', $audit->new_values['status']);
        $this->assertArrayHasKey('published_at', $audit->new_values);
        $this->assertNull($audit->new_values['published_at']);
    }

    public function test_published_events_remain_editable_to_the_owner_and_visible_in_the_shared_index(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->published()->create();
        Event::factory()->create(['owner_team_id' => $event->owner_team_id]);
        Event::factory()->published()->create();
        $owner = $this->member($event->owner);
        $payload = $this->payload($event);

        $this->actingAs($owner)->get(route('events.index', ['status' => 'published']))->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', 'published')->has('events.data', 2));
        $this->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page->where('can.edit', true));
        $this->get(route('events.edit', $event))->assertInertia(fn (Assert $page) => $page->where('event.status', 'published'));
        $this->put(route('events.update', $event), [...$payload, 'title' => 'Updated public briefing', 'status' => 'draft', 'published_at' => null])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'published', 'published_at' => '2026-09-09 12:00:00', 'title' => 'Updated public briefing']);
        $this->actingAsGuest()->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page->where('event.title', 'Updated public briefing'));
    }

    public function test_normal_event_creation_cannot_bypass_the_publication_action(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $fir = Team::factory()->create();
        $owner = $this->member($fir);
        $payload = $this->payload(Event::factory()->make(['owner_team_id' => $fir->id]));

        $this->actingAs($owner)->post(route('events.store'), [...$payload, 'status' => 'published', 'published_at' => now()->toIso8601String()])->assertSessionHasNoErrors();

        $event = Event::firstOrFail();
        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'draft', 'published_at' => null]);
        $this->actingAsGuest()->get(route('events.show', $event))->assertNotFound();
    }

    /** @return array<string, mixed> */
    private function payload(Event $event): array
    {
        return [
            'owner_team_id' => $event->owner_team_id, 'title' => $event->title, 'short_description' => $event->short_description,
            'description' => $event->description, 'airport_ids' => [Airport::factory()->create()->id], 'timezone' => $event->timezone,
            'local_start' => $event->local_start, 'local_end' => $event->local_end, 'recurrence' => $event->recurrence,
            'recurrence_interval' => $event->recurrence_interval, 'monthly_week' => $event->monthly_week,
            'recurrence_until' => $event->recurrence_until?->toDateString(), 'roster_enabled' => $event->roster_enabled,
        ];
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
