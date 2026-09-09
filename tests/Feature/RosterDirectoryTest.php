<?php

namespace Tests\Feature;

use App\Actions\Authorization\UpdateRoleAssignments;
use App\Models\Event;
use App\Models\EventCancellation;
use App\Models\EventCollaboration;
use App\Models\EventRoster;
use App\Models\Team;
use App\Models\User;
use App\RoleName;
use Database\Seeders\EventRosterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RosterDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_controller_directory_shows_opened_rosters_across_firs_and_hides_unopened_rosters(): void
    {
        $this->travelTo('2026-10-05 12:00:00');
        $visible = EventRoster::factory()->create(['opened_at' => now(), 'is_open' => false]);
        $other = EventRoster::factory()->create(['opened_at' => now(), 'is_open' => true]);
        $hidden = EventRoster::factory()->create();
        $user = $this->member($visible->event->owner, RoleName::Controller);
        app(UpdateRoleAssignments::class)->grant($user, RoleName::Controller, $hidden->event->owner);

        $this->actingAs($user)->get(route('rosters.index'))->assertInertia(fn (Assert $page) => $page
            ->component('events/rosters')->where('auth.can_view_rosters', true)
            ->has('rosters.data', 2)->where('rosters.data.0.id', $other->id)->where('rosters.data.1.id', $visible->id)
            ->where('rosters.data.1.is_open', false)->where('rosters.data.1.has_ended', true)
            ->where('rosters.data.1.occurrence', null));

        $this->get(route('events.show', $visible->event))->assertForbidden();
    }

    public function test_pending_collaboration_does_not_expose_unopened_roster_to_staff_but_acceptance_does(): void
    {
        $roster = EventRoster::factory()->create();
        $fir = Team::factory()->create();
        $collaboration = EventCollaboration::factory()->for($roster->event)->create(['team_id' => $fir->id]);
        $user = $this->member($fir, RoleName::VaccStaff);

        $this->actingAs($user)->get(route('rosters.index'))->assertInertia(fn (Assert $page) => $page->has('rosters.data', 0));
        $collaboration->update(['accepted_at' => now()]);
        $this->get(route('rosters.index'))->assertInertia(fn (Assert $page) => $page
            ->has('rosters.data', 1)->where('rosters.data.0.id', $roster->id));
    }

    public function test_staff_can_find_unopened_rosters_in_their_fir(): void
    {
        $roster = EventRoster::factory()->create();
        $user = $this->member($roster->event->owner, RoleName::VaccStaff);

        $this->actingAs($user)->get(route('rosters.index'))->assertInertia(fn (Assert $page) => $page
            ->has('rosters.data', 1)->where('rosters.data.0.id', $roster->id));
    }

    public function test_guests_and_pilots_cannot_access_roster_directory(): void
    {
        $this->get(route('rosters.index'))->assertRedirectToRoute('login');
        $this->actingAs(User::factory()->create())->get(route('rosters.index'))->assertForbidden();
    }

    public function test_directory_rejects_invalid_page_and_paginates(): void
    {
        $user = $this->member(Team::factory()->create(), RoleName::Administrator);
        EventRoster::factory()->count(13)->create();

        $this->actingAs($user)->get(route('rosters.index', ['page' => 'invalid']))->assertSessionHasErrors('page');
        $this->get(route('rosters.index'))->assertInertia(fn (Assert $page) => $page
            ->has('rosters.data', 12)->where('rosters.total', 13)->where('rosters.last_page', 2));
        $this->get(route('rosters.index', ['page' => 2]))->assertInertia(fn (Assert $page) => $page
            ->has('rosters.data', 1)->where('rosters.current_page', 2));
    }

    public function test_directory_keeps_one_entry_and_advances_to_the_next_uncancelled_occurrence(): void
    {
        $event = Event::factory()->weekly()->create();
        $roster = EventRoster::factory()->for($event)->open()->create();
        EventCancellation::factory()->for($event)->create(['occurrence_date' => '2026-10-11']);
        $this->actingAs($this->member($event->owner, RoleName::Controller));
        $this->travelTo('2026-10-04 20:59:59');

        $this->get(route('rosters.index'))->assertInertia(fn (Assert $page) => $page
            ->has('rosters.data', 1)->where('rosters.data.0.id', $roster->id)
            ->where('rosters.data.0.occurrence.date', '2026-10-04'));

        $this->travelTo('2026-10-04 21:00:00');
        $this->get(route('rosters.index'))->assertInertia(fn (Assert $page) => $page
            ->has('rosters.data', 1)->where('rosters.data.0.id', $roster->id)
            ->where('rosters.data.0.occurrence.date', '2026-10-18')->where('rosters.data.0.has_ended', false));

        $this->assertDatabaseCount('event_rosters', 1);
        $this->assertDatabaseCount('roster_bookings', 0);
    }

    public function test_sample_rosters_are_private_and_audited(): void
    {
        $this->seed(EventRosterSeeder::class);

        $this->assertDatabaseCount('event_rosters', 2);
        $this->assertDatabaseHas('event_rosters', ['mode' => 'pre_slotted', 'is_open' => false, 'opened_at' => null]);
        $this->assertDatabaseHas('event_rosters', ['mode' => 'open_interest', 'is_open' => false, 'opened_at' => null]);
        $this->assertDatabaseCount('roster_slots', 2);
        $this->assertDatabaseCount('roster_positions', 2);
        $this->assertDatabaseHas('audit_logs', ['subject_type' => 'roster', 'event' => 'created']);
    }

    private function member(Team $fir, RoleName $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        app(UpdateRoleAssignments::class)->grant($user, $role, $role->isGlobal() ? null : $fir);

        return $user;
    }
}
