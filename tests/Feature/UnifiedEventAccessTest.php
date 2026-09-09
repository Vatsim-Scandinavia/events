<?php

namespace Tests\Feature;

use App\Actions\Authorization\UpdateRoleAssignments;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventCollaboration;
use App\Models\EventRoster;
use App\Models\Team;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class UnifiedEventAccessTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith([RoleName::EventCoordinator, 'owner', true, true, true])]
    #[TestWith([RoleName::EventCoordinator, 'collaborator', true, true, false])]
    #[TestWith([RoleName::VaccStaff, 'owner', true, false, false])]
    #[TestWith([RoleName::EventCoordinator, 'unrelated', false, false, false])]
    #[TestWith([RoleName::Controller, 'owner', false, false, false])]
    #[TestWith([RoleName::Administrator, 'unrelated', true, true, true])]
    #[TestWith([null, 'guest', false, false, false])]
    public function test_shared_detail_only_includes_controls_for_authorized_staff(?RoleName $role, string $relationship, bool $staff, bool $edit, bool $owner): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->published()->rostered()->create();
        EventRoster::factory()->for($event)->create();
        if ($role !== null) {
            $fir = $relationship === 'owner' ? $event->owner : Team::factory()->create();
            if ($relationship === 'collaborator') {
                EventCollaboration::factory()->for($event)->accepted()->create(['team_id' => $fir->id]);
            }
            $this->actingAs($this->member($fir, $role));
        }

        $this->get(route('events.show', $event))->assertInertia(function (Assert $page) use ($event, $staff, $edit, $owner): void {
            $page->component('events/show')->where('event.id', $event->id)->has('event.description_html')
                ->missing('description_html')->missing('event.description')->missing('event.short_description')
                ->missing('event.local_end')->missing('event.owner_team_id')->missing('can_view_management');
            if ($staff) {
                $page->where('can.edit', $edit)->where('can.manage_owner', $owner)
                    ->where('can.publish', false)->where('can.unpublish', $owner)
                    ->where('event.roster_enabled', true)->where('event.roster_exists', true)
                    ->has('collaborations')->has('firs');
            } else {
                $page->missing('can')->missing('collaborations')->missing('firs')
                    ->missing('event.roster_enabled')->missing('event.roster_exists');
            }
        });
    }

    public function test_unified_index_combines_public_events_and_scoped_drafts_before_applying_filters(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $fir = Team::factory()->create();
        $coordinator = $this->member($fir);
        $owned = Event::factory()->create(['owner_team_id' => $fir->id, 'title' => 'Match private event']);
        Event::factory()->create(['owner_team_id' => $fir->id, 'title' => 'Other private event']);
        $published = Event::factory()->published()->create(['title' => 'Match public event']);
        Event::factory()->published()->create(['title' => 'Other public event']);
        $hidden = Event::factory()->create(['title' => 'Match hidden invitation event']);
        $invitation = EventCollaboration::factory()->for($hidden)->create(['team_id' => $fir->id]);
        EventCollaboration::factory()->create();

        $this->actingAs($coordinator)->get(route('events.index'))->assertInertia(fn (Assert $page) => $page
            ->component('events/index')->has('events.data', 4)->where('can_create', true)
            ->has('invitations', 1)->where('invitations.0.id', $invitation->id)->missing('invitations.0.title'));
        $this->get(route('events.index', ['search' => 'Match']))->assertInertia(fn (Assert $page) => $page->has('events.data', 2));
        $this->get(route('events.index', ['search' => 'Match', 'status' => 'draft']))->assertInertia(fn (Assert $page) => $page
            ->has('events.data', 1)->where('events.data.0.id', $owned->id));
        $this->get(route('events.index', ['search' => 'Match', 'status' => 'published']))->assertInertia(fn (Assert $page) => $page
            ->has('events.data', 1)->where('events.data.0.id', $published->id));

        $this->actingAsGuest()->get(route('events.index', ['search' => 'Match']))->assertInertia(fn (Assert $page) => $page
            ->has('events.data', 1)->where('events.data.0.id', $published->id)->missing('invitations')->missing('can_create'));
        $this->get(route('events.show', $hidden))->assertNotFound();
    }

    public function test_administrators_see_unpublished_drafts_even_without_fir_scoped_grants(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        Event::factory()->count(2)->create();
        Event::factory()->published()->create();
        $administrator = $this->member(Team::factory()->create(), RoleName::Administrator);

        $this->actingAs($administrator)->get(route('events.index'))->assertInertia(fn (Assert $page) => $page
            ->has('events.data', 3)->where('can_create', true));
        $this->get(route('events.index', ['status' => 'draft']))->assertInertia(fn (Assert $page) => $page
            ->has('events.data', 2)->where('events.data.0.status', 'draft')->where('events.data.1.status', 'draft'));
    }

    public function test_read_only_staff_get_their_private_events_without_creation_or_invitation_permissions(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->create();
        $staff = $this->member($event->owner, RoleName::VaccStaff);
        EventCollaboration::factory()->create(['team_id' => $event->owner_team_id]);

        $this->actingAs($staff)->get(route('events.index'))->assertInertia(fn (Assert $page) => $page
            ->has('events.data', 1)->where('events.data.0.id', $event->id)->where('can_create', false)->has('invitations', 0));
        $this->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page->where('can.edit', false)->where('can.publish', false));
        $this->put(route('events.update', $event), [])->assertForbidden();
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_requesting_staff_props_via_partial_inertia_reload_does_not_expose_them(bool $signedIn): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->published()->rostered()->create();
        EventRoster::factory()->for($event)->create();
        EventCollaboration::factory()->for($event)->create();
        if ($signedIn) {
            $this->actingAs($this->member(Team::factory()->create()));
        }
        $page = $this->get(route('events.show', $event));
        $auditCount = AuditLog::count();

        $this->withHeaders([
            'X-Inertia' => 'true', 'X-Inertia-Version' => $page->viewData('page')['version'],
            'X-Inertia-Partial-Component' => 'events/show', 'X-Inertia-Partial-Data' => 'event,collaborations,firs,can',
        ])->get(route('events.show', $event))->assertOk()->assertJsonPath('props.event.id', $event->id)
            ->assertJsonMissingPaths(['props.collaborations', 'props.firs', 'props.can', 'props.event.roster_enabled', 'props.event.roster_exists', 'props.event.description', 'props.event.local_end']);

        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    public function test_being_allowed_to_read_a_public_event_does_not_allow_mutations(): void
    {
        $this->travelTo('2026-09-09 12:00:00');
        $event = Event::factory()->published()->create();
        $outsider = $this->member(Team::factory()->create());
        $auditCount = AuditLog::count();

        $this->actingAs($outsider)->get(route('events.show', $event))->assertInertia(fn (Assert $page) => $page->where('event.id', $event->id)->missing('can'));
        $this->put(route('events.update', $event), [])->assertForbidden();
        $this->post(route('events.cancellations.store', $event), ['reason' => 'Unauthorized'])->assertForbidden();
        $this->delete(route('events.publication.destroy', $event))->assertForbidden();

        $this->assertDatabaseHas('events', ['id' => $event->id, 'status' => 'published', 'cancellation_reason' => null]);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }

    private function member(Team $fir, RoleName $role = RoleName::EventCoordinator): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        app(UpdateRoleAssignments::class)->grant($user, $role, $role->isGlobal() ? null : $fir);

        return $user;
    }
}
