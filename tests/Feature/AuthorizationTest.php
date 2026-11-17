<?php

namespace Tests\Feature;

use App\Auth\Authorization;
use App\Auth\Roles;
use App\Auth\Where;
use App\Enums\Permission;
use App\Models\Event;
use App\Models\Fir;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_role_held_in_one_fir_counts_there_and_anywhere_but_not_hub_wide_or_elsewhere(): void
    {
        $copenhagen = Fir::factory()->create();
        $sweden = Fir::factory()->create();
        $staff = User::factory()->staff($copenhagen)->create();
        $authorization = app(Authorization::class);

        $this->assertTrue($authorization->holds($staff, Permission::ManageEvents, Where::in($copenhagen)));
        $this->assertFalse($authorization->holds($staff, Permission::ManageEvents, Where::in($sweden)));
        $this->assertTrue($authorization->holds($staff, Permission::ManageEvents, Where::anywhere()));
        $this->assertFalse($authorization->holds($staff, Permission::ManageEvents, Where::hub()));
    }

    public function test_a_role_held_hub_wide_counts_everywhere(): void
    {
        $sweden = Fir::factory()->create();
        $vaccStaff = User::factory()->vaccStaff()->create();
        $authorization = app(Authorization::class);

        $this->assertTrue($authorization->holds($vaccStaff, Permission::ViewEvents, Where::hub()));
        $this->assertTrue($authorization->holds($vaccStaff, Permission::ViewEvents, Where::in($sweden)));
        $this->assertTrue($authorization->holds($vaccStaff, Permission::ViewEvents, Where::anywhere()));
    }

    public function test_the_permissions_held_hub_wide_leave_out_roles_held_in_one_fir(): void
    {
        $copenhagen = Fir::factory()->create();
        $user = User::factory()->vaccStaff()->withRole(Roles::EVENT_STAFF, $copenhagen)->create();
        $authorization = app(Authorization::class);

        $this->assertEqualsCanonicalizing([Permission::ViewEvents->value], $authorization->permissionsAt($user, Where::hub())->all());
        $this->assertEqualsCanonicalizing(
            [Permission::ViewEvents->value, Permission::ManageEvents->value, Permission::ManageStaffing->value],
            $authorization->permissionsAt($user, Where::in($copenhagen))->all(),
        );
    }

    public function test_the_firs_where_a_permission_is_held_are_listed_or_every_fir_when_held_hub_wide(): void
    {
        $copenhagen = Fir::factory()->create();
        Fir::factory()->create();
        $authorization = app(Authorization::class);

        $this->assertSame([$copenhagen->id], $authorization->firsWhere(User::factory()->staff($copenhagen)->create(), Permission::ManageEvents, Permission::ViewEvents));
        $this->assertNull($authorization->firsWhere(User::factory()->vaccStaff()->create(), Permission::ManageEvents, Permission::ViewEvents));
        $this->assertSame([], $authorization->firsWhere(User::factory()->create(), Permission::ManageEvents));
    }

    public function test_administrators_hold_every_permission_everywhere_without_storing_any(): void
    {
        $admin = User::factory()->admin()->create();
        $authorization = app(Authorization::class);

        $this->assertTrue(Role::findByName(Roles::ADMINISTRATOR)->permissions->isEmpty());
        $this->assertTrue($authorization->isAdministrator($admin));
        $this->assertTrue($authorization->holds($admin, Permission::ManageUsers, Where::hub()));
        $this->assertNull($authorization->firsWhere($admin, Permission::ManageEvents));
    }

    public function test_managing_users_in_one_fir_only_lets_someone_give_roles_there_that_allow_no_more_than_they_can_do(): void
    {
        $copenhagen = Fir::factory()->create();
        $sweden = Fir::factory()->create();
        $manager = User::factory()->staff($copenhagen)->create();
        Role::findByName(Roles::EVENT_STAFF)->update(['permissions' => [Permission::ManageEvents, Permission::ManageStaffing, Permission::ManageUsers]]);
        $manager->refresh();
        $authorization = app(Authorization::class);

        $this->assertTrue($authorization->mayGrant($manager, Role::findByName(Roles::EVENT_STAFF), Where::in($copenhagen)));
        $this->assertFalse($authorization->mayGrant($manager, Role::findByName(Roles::EVENT_STAFF), Where::in($sweden)));
        $this->assertFalse($authorization->mayGrant($manager, Role::findByName(Roles::EVENT_STAFF), Where::hub()));
        // vACC Staff views events, which Event Staff here doesn't, so giving it would gain a permission.
        $this->assertFalse($authorization->mayGrant($manager, Role::findByName(Roles::VACC_STAFF), Where::in($copenhagen)));
        $this->assertFalse($authorization->mayGrant($manager, Role::findByName(Roles::ADMINISTRATOR), Where::hub()));
        $this->assertSame([$copenhagen->id], $authorization->grantableIn($manager, Role::findByName(Roles::EVENT_STAFF), collect([$copenhagen, $sweden])));
    }

    public function test_controllers_for_an_event_hold_the_controller_role_hub_wide_or_in_its_fir(): void
    {
        $copenhagen = Fir::factory()->create();
        $sweden = Fir::factory()->create();
        $event = Event::factory()->create(['fir_id' => $copenhagen->id]);
        $hubWide = User::factory()->controller()->create();
        $here = User::factory()->withRole(Roles::CONTROLLER, $copenhagen)->create();
        $elsewhere = User::factory()->withRole(Roles::CONTROLLER, $sweden)->create();
        $admin = User::factory()->admin()->create();
        User::factory()->staff($copenhagen)->create();
        $authorization = app(Authorization::class);

        $this->assertEqualsCanonicalizing([$hubWide->id, $here->id, $admin->id], $authorization->controllersFor($event)->pluck('id')->all());
        $this->assertTrue($here->can('staff', $event));
        $this->assertFalse($elsewhere->can('staff', $event));
    }
}
