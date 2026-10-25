<?php

namespace Tests\Feature\Admin;

use App\Auth\Roles;
use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_built_in_roles_exist_with_the_right_permissions(): void
    {
        $this->assertEqualsCanonicalizing(Roles::all(), Role::findByName(Roles::ADMINISTRATOR)->permissions->pluck('value')->all());
        $this->assertEqualsCanonicalizing(
            [Permission::ManageEvents->value, Permission::ManageStaffing->value],
            Role::findByName(Roles::EVENT_STAFF)->permissions->pluck('value')->all(),
        );
        $this->assertSame([Permission::ViewEvents->value], Role::findByName(Roles::VACC_STAFF)->permissions->pluck('value')->all());
        $this->assertSame([Permission::BookPositions->value], Role::findByName(Roles::CONTROLLER)->permissions->pluck('value')->all());
    }

    public function test_the_existing_gates_follow_role_permissions(): void
    {
        $staff = User::factory()->staff()->create();
        $controller = User::factory()->controller()->create();

        $this->assertTrue($staff->can('manage-events'));
        $this->assertTrue($staff->can('manage-staffing'));
        $this->assertFalse($staff->can('manage-users'));
        $this->assertFalse($controller->can('manage-events'));

        // Taking a permission off the role takes it off everyone holding it.
        Role::findByName(Roles::EVENT_STAFF)->update(['permissions' => [Permission::ManageStaffing]]);
        $this->assertFalse($staff->fresh()->can('manage-events'));
        $this->actingAs($staff->fresh())->get('/events/create')->assertForbidden();
    }

    public function test_only_people_who_manage_users_can_open_the_user_pages(): void
    {
        $user = User::factory()->create();

        $this->get('/admin/users')->assertRedirect('/auth/vatsim');
        $this->actingAs(User::factory()->staff()->create())->get('/admin/users')->assertForbidden();
        $this->actingAs(User::factory()->staff()->create())->get("/admin/users/{$user->id}")->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/users')->assertOk();
    }

    public function test_users_can_be_searched_and_filtered_by_role(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Ada Admin', 'vatsim_cid' => 1000001]);
        User::factory()->staff()->create(['name' => 'Sam Staff', 'vatsim_cid' => 1234567]);
        User::factory()->create(['name' => 'Olly Observer', 'vatsim_cid' => 1000002]);

        $this->actingAs($admin)->get('/admin/users?search=1234')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Index')
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Sam Staff')
            ->where('users.data.0.roles', ['Event Staff'])
        );

        $staffRole = Role::findByName(Roles::EVENT_STAFF)->id;
        $this->actingAs($admin)->get("/admin/users?role={$staffRole}")->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Sam Staff')
        );

        $this->actingAs($admin)->get('/admin/users')->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 3)
            ->where('users.data.1.name', 'Olly Observer')
            ->where('users.data.1.roles', ['Pilot'])
        );
    }

    public function test_percent_and_underscore_in_a_search_match_only_themselves(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
        User::factory()->create(['name' => 'Sam_Staff']);
        User::factory()->create(['name' => 'SamXStaff']);
        User::factory()->create(['name' => '100% Olly']);

        $this->actingAs($admin)->get('/admin/users?search=Sam_')->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Sam_Staff')
        );
        $this->actingAs($admin)->get('/admin/users?search=%25')->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.name', '100% Olly')
        );
    }

    public function test_the_first_administrator_can_be_made_from_the_command_line(): void
    {
        $user = User::factory()->create(['vatsim_cid' => 1400000]);

        $this->artisan('users:make-admin', ['cid' => 1400000])->assertSuccessful();
        $this->assertTrue($this->holdsAcrossTheHub($user, Roles::ADMINISTRATOR));

        $this->artisan('users:make-admin', ['cid' => 999])->assertFailed();
    }

    private function holdsAcrossTheHub(User $user, string $role): bool
    {
        return $user->roles()->where('name', $role)->wherePivotNull('fir_id')->exists();
    }
}
