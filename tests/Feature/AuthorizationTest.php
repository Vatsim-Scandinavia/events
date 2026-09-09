<?php

namespace Tests\Feature;

use App\Actions\Authorization\UpdateRoleAssignments;
use App\Models\Team;
use App\Models\User;
use App\PermissionName;
use App\RoleName;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_users_without_assignments_fall_back_to_pilot_without_permissions(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $this->assertSame(['Pilot'], $user->effectiveRoleNames()->all());
        $this->assertFalse($user->can(PermissionName::ViewEvents, $team));
        $this->assertFalse($user->can(PermissionName::ManageEvents, $team));
        $this->assertFalse($user->can(PermissionName::ManageRoles));
        $this->assertDatabaseCount('model_has_roles', 0);
    }

    public function test_administrators_have_global_access_without_fir_membership(): void
    {
        $user = User::factory()->create();
        app(UpdateRoleAssignments::class)->grant($user, RoleName::Administrator);
        $teams = Team::factory()->count(2)->create();

        foreach ($teams as $team) {
            $this->assertTrue($user->can(PermissionName::ViewEvents, $team));
            $this->assertTrue($user->can(PermissionName::ManageEvents, $team));
        }

        $this->assertTrue($user->can(PermissionName::ManageRoles));
        $this->assertTrue($user->teams()->get()->isEmpty());
        $this->assertSame(['Administrator'], $user->effectiveRoleNames()->all());
    }

    #[TestWith([RoleName::EventCoordinator, true, true])]
    #[TestWith([RoleName::VaccStaff, true, false])]
    #[TestWith([RoleName::Controller, false, false])]
    public function test_fir_roles_only_receive_their_permissions_in_their_own_fir(RoleName $role, bool $view, bool $manage): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $otherTeam = Team::factory()->create();
        app(UpdateRoleAssignments::class)->grant($user, $role, $team);

        $this->assertSame($view, $user->can(PermissionName::ViewEvents, $team));
        $this->assertSame($manage, $user->can(PermissionName::ManageEvents, $team));
        $this->assertFalse($user->can(PermissionName::ViewEvents, $otherTeam));
        $this->assertFalse($user->can(PermissionName::ManageEvents, $otherTeam));
        $this->assertFalse($user->can(PermissionName::ManageEvents));
        $this->assertFalse($user->can(PermissionName::ManageRoles));
        $this->assertSame([$role->value], $user->effectiveRoleNames()->all());
    }

    public function test_different_fir_roles_do_not_leak_through_loaded_relations_or_team_context(): void
    {
        $user = User::factory()->create();
        $firstTeam = Team::factory()->create();
        $secondTeam = Team::factory()->create();
        $assignments = app(UpdateRoleAssignments::class);
        $assignments->grant($user, RoleName::EventCoordinator, $firstTeam);
        $assignments->grant($user, RoleName::VaccStaff, $secondTeam);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($firstTeam->id);
        $user->load('roles', 'permissions');

        $this->assertFalse($user->can(PermissionName::ManageEvents, $secondTeam));
        $this->assertTrue($user->can(PermissionName::ViewEvents, $secondTeam));
        $this->assertTrue($user->can(PermissionName::ManageEvents, $firstTeam));
        $this->assertSame($firstTeam->id, $registrar->getPermissionsTeamId());
        $this->assertEqualsCanonicalizing([$firstTeam->id, $secondTeam->id], $user->teams()->pluck('teams.id')->all());
    }

    public function test_removing_a_role_only_affects_its_fir_and_last_removal_restores_pilot(): void
    {
        $user = User::factory()->create();
        $firstTeam = Team::factory()->create();
        $secondTeam = Team::factory()->create();
        $assignments = app(UpdateRoleAssignments::class);
        $assignments->grant($user, RoleName::EventCoordinator, $firstTeam);
        $assignments->grant($user, RoleName::EventCoordinator, $secondTeam);

        $assignments->revoke($user, RoleName::EventCoordinator, $firstTeam);

        $this->assertFalse($user->can(PermissionName::ManageEvents, $firstTeam));
        $this->assertTrue($user->can(PermissionName::ManageEvents, $secondTeam));
        $this->assertSame(['Event Coordinator'], $user->effectiveRoleNames()->all());

        $assignments->revoke($user, RoleName::EventCoordinator, $secondTeam);

        $this->assertSame(['Pilot'], $user->effectiveRoleNames()->all());
        $this->assertFalse($user->can(PermissionName::ManageEvents, $secondTeam));
        $this->assertDatabaseCount('model_has_roles', 0);
    }

    public function test_revoking_global_administrator_access_takes_effect_on_existing_user_instances(): void
    {
        $user = User::factory()->create();
        $assignments = app(UpdateRoleAssignments::class);
        $assignments->grant($user, RoleName::Administrator);
        $assignments->grant(User::factory()->create(), RoleName::Administrator);
        $otherInstance = $user->fresh();
        $this->assertTrue($otherInstance->can(PermissionName::ManageRoles));

        $assignments->revoke($user, RoleName::Administrator);

        $this->assertFalse($otherInstance->can(PermissionName::ManageRoles));
        $this->assertSame(['Pilot'], $otherInstance->effectiveRoleNames()->all());
    }

    #[TestWith([RoleName::Administrator, true])]
    #[TestWith([RoleName::EventCoordinator, false])]
    #[TestWith([RoleName::VaccStaff, false])]
    #[TestWith([RoleName::Controller, false])]
    #[TestWith([RoleName::Pilot, false])]
    public function test_invalid_role_scopes_are_rejected_without_granting_access(RoleName $role, bool $withTeam): void
    {
        $user = User::factory()->create();
        $team = $withTeam ? Team::factory()->create() : null;
        $this->expectException(InvalidArgumentException::class);

        try {
            app(UpdateRoleAssignments::class)->grant($user, $role, $team);
        } finally {
            $this->assertDatabaseCount('role_grants', 0);
            $this->assertDatabaseCount('model_has_roles', 0);
        }
    }

    public function test_unsaved_firs_cannot_be_used_to_grant_roles(): void
    {
        $user = User::factory()->create();
        $this->expectException(InvalidArgumentException::class);

        app(UpdateRoleAssignments::class)->grant($user, RoleName::Controller, Team::factory()->make());
    }

    public function test_role_seeding_is_repeatable_and_preserves_existing_assignments(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        app(UpdateRoleAssignments::class)->grant($user, RoleName::EventCoordinator, $team);

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertDatabaseCount('roles', 5);
        $this->assertDatabaseCount('permissions', 5);
        $this->assertTrue($user->can(PermissionName::ManageEvents, $team));
    }

    public function test_main_database_seeder_initializes_permissions_when_model_events_are_suppressed(): void
    {
        Role::query()->delete();
        Permission::query()->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->seed(DatabaseSeeder::class);

        $user = User::factory()->create();
        $team = Team::factory()->create();
        app(UpdateRoleAssignments::class)->grant($user, RoleName::EventCoordinator, $team);
        $this->assertTrue($user->can(PermissionName::ManageEvents, $team));
        $this->assertDatabaseCount('roles', 5);
    }

    public function test_server_console_can_bootstrap_an_existing_administrator_idempotently(): void
    {
        $user = User::factory()->create();

        $this->artisan('authorization:grant-administrator', ['cid' => $user->cid])->assertSuccessful();
        $this->artisan('authorization:grant-administrator', ['cid' => $user->cid])->assertSuccessful();

        $this->assertTrue($user->can(PermissionName::ManageRoles));
        $this->assertDatabaseCount('role_grants', 1);
        $this->assertDatabaseCount('model_has_roles', 1);
    }

    public function test_server_console_does_not_create_accounts_for_unknown_cids(): void
    {
        $this->artisan('authorization:grant-administrator', ['cid' => 1234567])->assertFailed();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('role_grants', 0);
    }

    public function test_deleting_a_user_cleans_up_global_and_fir_assignments(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $assignments = app(UpdateRoleAssignments::class);
        $assignments->grant($user, RoleName::Administrator);
        $assignments->grant($user, RoleName::Controller, $team);

        $user->delete();

        $this->assertDatabaseCount('role_grants', 0);
        $this->assertDatabaseCount('model_has_roles', 0);
        $this->assertModelExists($team);
    }
}
