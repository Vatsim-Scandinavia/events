<?php

namespace Tests\Feature\Admin;

use App\Auth\Roles;
use App\Enums\Permission;
use App\Models\Fir;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoleAssignmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_give_a_role_across_the_hub_or_in_one_fir_and_take_it_away(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->rated()->create(['name' => 'Kim']);
        $copenhagen = Fir::factory()->create(['icao' => 'EKDK', 'name' => 'Copenhagen']);
        $staff = Role::findByName(Roles::EVENT_STAFF);

        $this->actingAs($admin)->post("/admin/users/{$user->id}/roles", ['role_id' => $staff->id, 'fir_id' => $copenhagen->id])
            ->assertRedirect("/admin/users/{$user->id}")
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/admin/users/{$user->id}/roles", ['role_id' => Role::findByName(Roles::VACC_STAFF)->id, 'fir_id' => null])
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->can('manage-events', $copenhagen));
        $this->assertFalse($user->fresh()->can('manage-events', Fir::factory()->create(['icao' => 'ESAA'])));

        $this->actingAs($admin)->get("/admin/users/{$user->id}")->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Edit')
            ->where('user.roles', ['Event Staff · EKDK', 'vACC Staff'])
            ->has('assignments', 2)
            ->where('assignments.0.role', Roles::VACC_STAFF)
            ->where('assignments.0.fir', null)
            ->where('assignments.1.role', Roles::EVENT_STAFF)
            ->where('assignments.1.fir', ['icao' => 'EKDK', 'name' => 'Copenhagen'])
            ->where('assignments.1.canRemove', true)
            ->where('firs.0.icao', 'EKDK')
        );

        $assignment = $user->roleAssignments()->where('fir_id', $copenhagen->id)->sole();
        $this->actingAs($admin)->delete("/admin/users/{$user->id}/roles/{$assignment->id}")
            ->assertRedirect("/admin/users/{$user->id}");

        $this->assertModelMissing($assignment);
        $this->assertSame(1, $user->roleAssignments()->count());
    }

    public function test_the_same_role_cannot_be_given_twice_in_the_same_place(): void
    {
        $admin = User::factory()->admin()->create();
        $copenhagen = Fir::factory()->create(['icao' => 'EKDK']);
        $user = User::factory()->staff($copenhagen)->create(['name' => 'Kim']);

        $this->actingAs($admin)
            ->post("/admin/users/{$user->id}/roles", ['role_id' => Role::findByName(Roles::EVENT_STAFF)->id, 'fir_id' => $copenhagen->id])
            ->assertSessionHasErrors(['roles' => 'Kim already has Event Staff · EKDK.']);

        // Across the hub is a different place.
        $this->actingAs($admin)
            ->post("/admin/users/{$user->id}/roles", ['role_id' => Role::findByName(Roles::EVENT_STAFF)->id])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, $user->roleAssignments()->count());
    }

    public function test_administrator_can_only_be_given_across_the_hub(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->post("/admin/users/{$user->id}/roles", ['role_id' => Role::findByName(Roles::ADMINISTRATOR)->id, 'fir_id' => Fir::factory()->create()->id])
            ->assertSessionHasErrors(['roles' => 'Administrator always applies across the hub.']);

        $this->assertSame(0, $user->roleAssignments()->count());
    }

    public function test_you_cannot_hand_out_or_take_away_a_role_that_allows_more_than_you_can_do(): void
    {
        // A user manager who can't manage events can't make anyone Event Staff, or an Administrator.
        $manager = User::factory()->create();
        $manager->assignRole(Role::create(['name' => 'User Manager'])->givePermissionTo(Permission::ManageUsers->value));
        $user = User::factory()->create();

        foreach ([Roles::EVENT_STAFF, Roles::ADMINISTRATOR] as $role) {
            $this->actingAs($manager)
                ->post("/admin/users/{$user->id}/roles", ['role_id' => Role::findByName($role)->id])
                ->assertSessionHasErrors(['roles' => "You can’t give or take away {$role}, because it allows more than you can do yourself."]);
        }
        $this->assertSame(0, $user->roleAssignments()->count());

        // Nor take one away from someone else.
        $staff = User::factory()->staff()->create();
        $assignment = $staff->roleAssignments()->sole();
        $this->actingAs($manager)->delete("/admin/users/{$staff->id}/roles/{$assignment->id}")->assertSessionHasErrors('roles');
        $this->assertModelExists($assignment);

        $this->actingAs($manager)->get("/admin/users/{$staff->id}")->assertInertia(fn (Assert $page) => $page
            ->where('assignments.0.canRemove', false)
            ->where('roles.0.name', Roles::ADMINISTRATOR)
            ->where('roles.0.grantableIn', [])
            ->where('roles.0.hubOnly', true)
            ->where('roles.3.name', 'User Manager')
            ->where('roles.3.grantableIn', [null])
        );
    }

    public function test_the_last_administrator_cannot_be_removed(): void
    {
        $admin = User::factory()->admin()->create();
        $assignment = $admin->roleAssignments()->sole();

        $this->actingAs($admin)->delete("/admin/users/{$admin->id}/roles/{$assignment->id}")
            ->assertSessionHasErrors(['roles' => 'The hub needs at least one Administrator, so this person has to stay one.']);
        $this->assertModelExists($assignment);

        // With a second Administrator, either can step down.
        User::factory()->admin()->create();
        $this->actingAs($admin)->delete("/admin/users/{$admin->id}/roles/{$assignment->id}")->assertSessionHasNoErrors();
        $this->assertModelMissing($assignment);
    }

    public function test_an_assignment_can_only_be_removed_through_its_own_user(): void
    {
        $admin = User::factory()->admin()->create();
        $staff = User::factory()->staff()->create();
        $someoneElse = User::factory()->create();

        $this->actingAs($admin)
            ->delete("/admin/users/{$someoneElse->id}/roles/{$staff->roleAssignments()->sole()->id}")
            ->assertNotFound();
    }

    public function test_only_people_who_manage_users_can_change_roles(): void
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->staff()->create())
            ->post("/admin/users/{$user->id}/roles", ['role_id' => Role::findByName(Roles::EVENT_STAFF)->id])
            ->assertForbidden();
    }

    public function test_users_can_be_filtered_by_a_role_held_in_a_fir(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
        $copenhagen = Fir::factory()->create();
        $sweden = Fir::factory()->create();
        User::factory()->staff($copenhagen)->create(['name' => 'Copenhagen Staff']);
        User::factory()->staff($sweden)->create(['name' => 'Sweden Staff']);
        User::factory()->staff()->create(['name' => 'Hub Staff']);
        User::factory()->vaccStaff($copenhagen)->create(['name' => 'Copenhagen Director']);
        $staffRole = Role::findByName(Roles::EVENT_STAFF)->id;

        $this->actingAs($admin)->get("/admin/users?role={$staffRole}&fir={$copenhagen->id}")->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Copenhagen Staff')
            ->where('filters.fir', $copenhagen->id)
        );

        $this->actingAs($admin)->get("/admin/users?fir={$copenhagen->id}")->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 2)
            ->where('users.data.0.name', 'Copenhagen Director')
            ->where('users.data.1.name', 'Copenhagen Staff')
        );
    }
}
