<?php

namespace Tests\Feature;

use App\Auth\Roles;
use App\Enums\Permission;
use App\Models\Fir;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

class RoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_role_held_in_one_fir_only_counts_in_that_fir(): void
    {
        $copenhagen = Fir::factory()->create();
        $sweden = Fir::factory()->create();
        $staff = User::factory()->staff($copenhagen)->create();

        $this->assertTrue(Gate::forUser($staff)->allows('manage-events', $copenhagen));
        $this->assertFalse(Gate::forUser($staff)->allows('manage-events', $sweden));
        // Without a FIR, it's enough to hold the permission somewhere.
        $this->assertTrue(Gate::forUser($staff)->allows('manage-events'));
    }

    public function test_a_role_held_across_the_hub_counts_in_every_fir(): void
    {
        $staff = User::factory()->staff()->create();

        $this->assertTrue(Gate::forUser($staff)->allows('manage-events', Fir::factory()->create()));
        $this->assertFalse(Gate::forUser($staff)->allows('manage-users', Fir::factory()->create()));
    }

    public function test_administrators_pass_every_check_even_without_the_permission(): void
    {
        Role::findByName(Roles::ADMINISTRATOR)->update(['permissions' => []]);
        $admin = User::factory()->admin()->create();
        Gate::define('something-new', fn () => false);

        $this->assertTrue(Gate::forUser($admin)->allows('something-new'));
        $this->assertTrue($admin->hasPermission(Permission::ManageUsers, Fir::factory()->create()));
    }

    public function test_administrator_cannot_be_held_in_a_single_fir(): void
    {
        $this->expectException(InvalidArgumentException::class);

        User::factory()->create()->assignRole(Roles::ADMINISTRATOR, Fir::factory()->create());
    }

    public function test_holding_a_role_twice_in_the_same_place_keeps_one_assignment(): void
    {
        $fir = Fir::factory()->create();
        $user = User::factory()->create();

        $user->assignRole(Roles::EVENT_STAFF);
        $user->assignRole(Roles::EVENT_STAFF);
        $user->assignRole(Roles::EVENT_STAFF, $fir);
        $user->assignRole(Roles::EVENT_STAFF, $fir);

        $this->assertSame(2, $user->roleAssignments()->count());
    }

    public function test_people_without_a_role_are_shown_as_pilots(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.roles', [Roles::PILOT])
        );

        $staff = User::factory()->staff(Fir::factory()->create(['icao' => 'EKDK']))->vaccStaff()->create();

        $this->actingAs($staff)->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.roles', ['Event Staff · EKDK', 'vACC Staff'])
            ->where('auth.user.can.manage-events', true)
            ->where('auth.user.can.view-events', true)
            ->where('auth.user.can.manage-users', false)
        );
    }

    public function test_the_database_refuses_a_second_hub_wide_assignment_of_the_same_role(): void
    {
        $user = User::factory()->create();
        $staff = Role::findByName(Roles::EVENT_STAFF);
        RoleAssignment::query()->create(['user_id' => $user->id, 'role_id' => $staff->id, 'fir_id' => null]);

        $this->expectException(UniqueConstraintViolationException::class);

        RoleAssignment::query()->create(['user_id' => $user->id, 'role_id' => $staff->id, 'fir_id' => null]);
    }

    public function test_the_database_refuses_a_second_assignment_of_the_same_role_in_the_same_fir(): void
    {
        $user = User::factory()->create();
        $staff = Role::findByName(Roles::EVENT_STAFF);
        $copenhagen = Fir::factory()->create();
        RoleAssignment::query()->create(['user_id' => $user->id, 'role_id' => $staff->id, 'fir_id' => $copenhagen->id]);

        $this->expectException(UniqueConstraintViolationException::class);

        RoleAssignment::query()->create(['user_id' => $user->id, 'role_id' => $staff->id, 'fir_id' => $copenhagen->id]);
    }

    public function test_the_same_role_can_still_be_held_across_the_hub_and_in_several_firs(): void
    {
        $user = User::factory()->create();

        $user->assignRole(Roles::EVENT_STAFF);
        $user->assignRole(Roles::EVENT_STAFF, Fir::factory()->create());
        $user->assignRole(Roles::EVENT_STAFF, Fir::factory()->create());

        $this->assertSame(3, $user->roleAssignments()->count());
    }

    public function test_giving_a_role_someone_already_has_reports_it_instead_of_failing(): void
    {
        $admin = User::factory()->admin()->create();
        $kim = User::factory()->create(['name' => 'Kim']);
        $staff = Role::findByName(Roles::EVENT_STAFF);
        // Another admin's request got there first, after this one checked.
        $kim->assignRole($staff);

        $this->assertFalse($kim->assignRole($staff)->wasRecentlyCreated);
        // The race itself: the insert hits the unique index and falls back to the existing row.
        $this->assertFalse($kim->roleAssignments()->createOrFirst(['role_id' => $staff->id, 'fir_id' => null])->wasRecentlyCreated);

        $this->actingAs($admin)->post("/admin/users/{$kim->id}/roles", ['role_id' => $staff->id])
            ->assertSessionHasErrors(['roles' => 'Kim already has Event Staff.']);
        $this->assertSame(1, $kim->roleAssignments()->count());
    }
}
