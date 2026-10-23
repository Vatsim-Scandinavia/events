<?php

namespace Tests\Feature\Admin;

use App\Auth\Roles;
use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_people_who_manage_roles_can_open_the_role_pages(): void
    {
        $this->get('/admin/roles')->assertRedirect('/auth/vatsim');
        $this->actingAs(User::factory()->staff()->create())->get('/admin/roles')->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())->get('/admin/roles')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Roles/Index')
            ->has('roles', 4)
            ->where('roles.0.name', Roles::ADMINISTRATOR)
            ->where('roles.0.builtIn', true)
            ->where('roles.2.name', Roles::EVENT_STAFF)
            ->where('roles.2.users', 1)
            ->where('roles.2.builtIn', true)
        );
    }

    public function test_an_admin_can_create_edit_and_delete_a_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/roles', [
            'name' => 'Roster Team',
            'permissions' => [Permission::ManageStaffing->value],
        ])->assertRedirect('/admin/roles');

        $role = Role::findByName('Roster Team');
        $this->assertSame([Permission::ManageStaffing->value], $role->permissions->pluck('name')->all());

        $this->actingAs($admin)->put("/admin/roles/{$role->id}", [
            'name' => 'Rostering',
            'permissions' => [Permission::ManageStaffing->value, Permission::ManageEvents->value],
        ])->assertRedirect('/admin/roles');

        $role->refresh();
        $this->assertSame('Rostering', $role->name);
        $this->assertEqualsCanonicalizing([Permission::ManageStaffing->value, Permission::ManageEvents->value], $role->permissions->pluck('name')->all());

        $member = User::factory()->create();
        $member->assignRole($role);

        $this->actingAs($admin)->delete("/admin/roles/{$role->id}")->assertRedirect('/admin/roles');
        $this->assertDatabaseMissing('roles', ['name' => 'Rostering']);
        $this->assertFalse($member->fresh()->can('manage-staffing'));
    }

    public function test_role_names_are_unique_and_permissions_must_exist(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/roles', ['name' => 'Event Staff', 'permissions' => []])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post('/admin/roles', ['name' => 'Odd', 'permissions' => ['launch-rockets']])->assertSessionHasErrors('permissions.0');
    }

    public function test_the_administrator_role_cannot_be_changed_or_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $role = Role::findByName(Roles::ADMINISTRATOR);

        $this->actingAs($admin)->put("/admin/roles/{$role->id}", ['name' => 'Boss', 'permissions' => []])->assertSessionHasErrors('role');
        $this->actingAs($admin)->delete("/admin/roles/{$role->id}")->assertSessionHasErrors('role');

        $role->refresh();
        $this->assertSame(Roles::ADMINISTRATOR, $role->name);
        $this->assertEqualsCanonicalizing(Roles::all(), $role->permissions->pluck('name')->all());
    }

    public function test_other_built_in_roles_keep_their_name_but_their_permissions_can_change(): void
    {
        $admin = User::factory()->admin()->create();
        $role = Role::findByName(Roles::VACC_STAFF);

        $this->actingAs($admin)
            ->put("/admin/roles/{$role->id}", ['name' => 'Board', 'permissions' => []])
            ->assertSessionHasErrors(['name' => 'vACC Staff is built in, so it can’t be renamed.']);
        $this->actingAs($admin)
            ->delete("/admin/roles/{$role->id}")
            ->assertSessionHasErrors(['role' => 'vACC Staff is built in, so it can’t be deleted.']);

        $this->actingAs($admin)
            ->put("/admin/roles/{$role->id}", ['name' => Roles::VACC_STAFF, 'permissions' => [Permission::ViewEvents->value, Permission::ManageStaffing->value]])
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            [Permission::ViewEvents->value, Permission::ManageStaffing->value],
            $role->fresh()->permissions->pluck('name')->all(),
        );
    }

    public function test_you_can_only_build_roles_from_permissions_you_hold(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(Role::create(['name' => 'Role Manager'])->givePermissionTo([
            Permission::ManageRoles->value,
            Permission::ManageStaffing->value,
        ]));

        $this->actingAs($manager)->post('/admin/roles', [
            'name' => 'Sneaky',
            'permissions' => [Permission::ManageUsers->value],
        ])->assertSessionHasErrors('permissions');
        $this->assertDatabaseMissing('roles', ['name' => 'Sneaky']);

        // Event Staff can manage events, which this manager can't, so it's out of reach.
        $staff = Role::findByName(Roles::EVENT_STAFF);
        $this->actingAs($manager)->put("/admin/roles/{$staff->id}", ['name' => 'Event Staff', 'permissions' => []])->assertSessionHasErrors('role');
        $this->actingAs($manager)->delete("/admin/roles/{$staff->id}")->assertSessionHasErrors('role');

        $this->actingAs($manager)->get("/admin/roles/{$staff->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Roles/Form')
            ->where('role.canChange', false)
            ->where('permissions.1.value', Permission::ManageStaffing->value)
            ->where('permissions.1.canGrant', true)
            ->where('permissions.2.canGrant', false)
        );

        $this->actingAs($manager)->post('/admin/roles', [
            'name' => 'Rostering',
            'permissions' => [Permission::ManageStaffing->value],
        ])->assertSessionHasNoErrors();
    }
}
