<?php

namespace Tests\Feature\Admin;

use App\Auth\Roles;
use App\Enums\Permission;
use App\Models\EventType;
use App\Models\Fir;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission as PermissionModel;
use Tests\TestCase;

class HubAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_holding_every_permission_across_the_hub_still_leaves_hub_configuration_to_administrators(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(Role::create(['name' => 'Everything but Administrator'])->givePermissionTo(Roles::all()));
        $fir = Fir::factory()->create();
        $type = EventType::query()->firstOrFail();
        $role = Role::findByName(Roles::EVENT_STAFF);

        $this->actingAs($manager);

        foreach (['/admin/roles', '/admin/roles/create', "/admin/roles/{$role->id}/edit", '/admin/firs', "/admin/firs/{$fir->id}/edit", '/admin/event-types', '/admin/settings'] as $page) {
            $this->get($page)->assertForbidden();
        }

        $this->post('/admin/roles', ['name' => 'Sneaky', 'permissions' => []])->assertForbidden();
        $this->put("/admin/roles/{$role->id}", ['name' => $role->name, 'permissions' => []])->assertForbidden();
        $this->delete("/admin/roles/{$role->id}")->assertForbidden();
        $this->post('/admin/firs', ['icao' => 'ESAA', 'name' => 'Sweden'])->assertForbidden();
        $this->delete("/admin/firs/{$fir->id}")->assertForbidden();
        $this->post('/admin/event-types', ['name' => 'Tour'])->assertForbidden();
        $this->delete("/admin/event-types/{$type->id}")->assertForbidden();
        $this->post('/admin/settings', ['name' => 'Hijacked'])->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'Sneaky']);
        $this->assertDatabaseHas('firs', ['id' => $fir->id]);
        $this->assertNotEmpty($role->fresh()->permissions);
    }

    public function test_only_administrators_can_give_or_take_away_administrator(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(Role::create(['name' => 'Everything but Administrator'])->givePermissionTo(Roles::all()));
        $administrator = Role::findByName(Roles::ADMINISTRATOR);
        $someone = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $assignment = $admin->roleAssignments()->sole();
        // A second Administrator, so the rule that the hub keeps one can't be what refuses the removal.
        User::factory()->admin()->create();

        $this->actingAs($manager)->post("/admin/users/{$someone->id}/roles", ['role_id' => $administrator->id])->assertSessionHasErrors('roles');
        $this->actingAs($manager)->delete("/admin/users/{$admin->id}/roles/{$assignment->id}")->assertSessionHasErrors('roles');

        $this->assertFalse($someone->fresh()->isAdministrator());
        $this->assertTrue($admin->fresh()->isAdministrator());
    }

    public function test_roles_can_no_longer_grant_hub_configuration(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/roles/create')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Roles/Form')
            ->where('permissions', fn ($permissions) => collect($permissions)->pluck('value')->all() === Roles::all())
        );

        $this->actingAs($admin)->post('/admin/roles', ['name' => 'Settings Team', 'permissions' => ['manage-settings']])
            ->assertSessionHasErrors('permissions.0');
        $this->actingAs($admin)->post('/admin/roles', ['name' => 'Role Team', 'permissions' => ['manage-roles']])
            ->assertSessionHasErrors('permissions.0');
    }

    public function test_migrating_takes_the_hub_configuration_permissions_away_from_every_role(): void
    {
        $settings = PermissionModel::findOrCreate('manage-settings', 'web');
        $roles = PermissionModel::findOrCreate('manage-roles', 'web');
        $team = Role::create(['name' => 'Settings Team'])->givePermissionTo([$settings, $roles, Permission::ManageEvents->value]);
        Role::findByName(Roles::ADMINISTRATOR)->givePermissionTo([$settings, $roles]);

        (require database_path('migrations/2026_10_23_162642_remove_hub_configuration_permissions.php'))->up();

        $this->assertDatabaseMissing('permissions', ['name' => 'manage-settings']);
        $this->assertDatabaseMissing('permissions', ['name' => 'manage-roles']);
        $this->assertSame([Permission::ManageEvents->value], $team->fresh()->permissions->pluck('name')->all());
        $this->assertEqualsCanonicalizing(Roles::all(), Role::findByName(Roles::ADMINISTRATOR)->permissions->pluck('name')->all());
    }

    public function test_rolling_back_gives_the_permissions_back_to_administrator_only(): void
    {
        $team = Role::create(['name' => 'Settings Team'])->givePermissionTo(Permission::ManageEvents->value);

        (require database_path('migrations/2026_10_23_162642_remove_hub_configuration_permissions.php'))->down();

        $this->assertEqualsCanonicalizing(
            [...Roles::all(), 'manage-roles', 'manage-settings'],
            Role::findByName(Roles::ADMINISTRATOR)->fresh()->permissions->pluck('name')->all(),
        );
        $this->assertSame([Permission::ManageEvents->value], $team->fresh()->permissions->pluck('name')->all());
    }

    public function test_only_administrators_are_shown_the_hub_configuration_sections(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole(Role::create(['name' => 'User Manager'])->givePermissionTo(Permission::ManageUsers->value));

        $this->actingAs($manager)->get('/admin/users')->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.isAdministrator', false)
            ->where('auth.user.can.manage-users', true)
        );

        $this->actingAs(User::factory()->admin()->create())->get('/admin/users')->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.isAdministrator', true)
        );

        $this->actingAs(User::factory()->staff()->create())->get('/events')->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.isAdministrator', false)
            ->where('auth.user.can.manage-users', false)
        );
    }
}
