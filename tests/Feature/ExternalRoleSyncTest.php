<?php

namespace Tests\Feature;

use App\Actions\Authorization\UpdateRoleAssignments;
use App\Models\Team;
use App\Models\User;
use App\PermissionName;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExternalRoleSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        config(['authorization.external_role_sources' => [
            'handover' => ['Event Coordinator', 'vACC Staff', 'Controller'],
            'other' => ['Controller'],
        ]]);
    }

    public function test_source_revocation_preserves_overlapping_manual_and_other_source_grants(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $assignments = app(UpdateRoleAssignments::class);
        $assignments->grant($user, RoleName::EventCoordinator, $team);
        $assignments->syncExternal($user, 'handover', [
            ['role' => RoleName::EventCoordinator, 'team' => $team],
            ['role' => RoleName::Controller, 'team' => $team],
        ]);
        $assignments->syncExternal($user, 'other', [['role' => RoleName::Controller, 'team' => $team]]);

        $assignments->syncExternal($user, 'handover', []);

        $this->assertTrue($user->can(PermissionName::ManageEvents, $team));
        $this->assertSame(['Controller', 'Event Coordinator'], $user->effectiveRoleNames()->all());
        $this->assertDatabaseCount('role_grants', 2);
        $this->assertDatabaseCount('model_has_roles', 2);
    }

    public function test_manual_revocation_preserves_a_role_still_granted_by_an_external_source(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $assignments = app(UpdateRoleAssignments::class);
        $assignments->syncExternal($user, 'handover', [['role' => RoleName::EventCoordinator, 'team' => $team]]);
        $assignments->grant($user, RoleName::EventCoordinator, $team);

        $assignments->revoke($user, RoleName::EventCoordinator, $team);

        $this->assertTrue($user->can(PermissionName::ManageEvents, $team));
        $this->assertDatabaseCount('role_grants', 1);
        $this->assertDatabaseHas('role_grants', ['source' => 'handover']);
    }

    #[TestWith(['manual', 'handover'])]
    #[TestWith(['handover', 'manual'])]
    public function test_the_final_administrator_can_lose_one_source_when_another_source_remains(string $removedSource, string $remainingSource): void
    {
        config(['authorization.external_role_sources.handover' => ['Administrator']]);
        $user = User::factory()->create();
        $assignments = app(UpdateRoleAssignments::class);
        $assignments->grant($user, RoleName::Administrator);
        $assignments->syncExternal($user, 'handover', [['role' => RoleName::Administrator, 'team' => null]]);

        if ($removedSource === 'manual') {
            $assignments->revoke($user, RoleName::Administrator);
        } else {
            $assignments->syncExternal($user, $removedSource, []);
        }

        $this->assertTrue($user->fresh()->isAdministrator());
        $this->assertDatabaseCount('role_grants', 1);
        $this->assertDatabaseHas('role_grants', ['user_id' => $user->cid, 'source' => $remainingSource]);
        $this->assertDatabaseCount('model_has_roles', 1);
        $this->assertDatabaseCount('audit_logs', 3);
    }

    public function test_an_external_administrator_can_be_replaced_in_the_same_complete_snapshot(): void
    {
        config(['authorization.external_role_sources.handover' => ['Administrator']]);
        $user = User::factory()->create();
        $assignments = app(UpdateRoleAssignments::class);
        $snapshot = [['role' => RoleName::Administrator, 'team' => null]];
        $assignments->syncExternal($user, 'handover', $snapshot);

        $assignments->syncExternal($user, 'handover', $snapshot);

        $this->assertTrue($user->fresh()->isAdministrator());
        $this->assertDatabaseCount('role_grants', 1);
        $this->assertDatabaseCount('model_has_roles', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_manual_administrator_revocation_counts_administrators_from_external_sources(): void
    {
        config(['authorization.external_role_sources.handover' => ['Administrator']]);
        $user = User::factory()->create();
        $otherAdministrator = User::factory()->create();
        $assignments = app(UpdateRoleAssignments::class);
        $assignments->grant($user, RoleName::Administrator);
        $assignments->syncExternal($otherAdministrator, 'handover', [['role' => RoleName::Administrator, 'team' => null]]);

        $assignments->revoke($user, RoleName::Administrator);

        $this->assertFalse($user->fresh()->isAdministrator());
        $this->assertTrue($otherAdministrator->fresh()->isAdministrator());
        $this->assertDatabaseMissing('role_grants', ['user_id' => $user->cid]);
        $this->assertDatabaseCount('model_has_roles', 1);
    }

    public function test_external_sync_that_removes_the_final_administrator_rolls_back_its_entire_snapshot(): void
    {
        config(['authorization.external_role_sources.handover' => ['Administrator', 'Controller', 'Event Coordinator']]);
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $assignments = app(UpdateRoleAssignments::class);
        $assignments->syncExternal($user, 'handover', [
            ['role' => RoleName::Administrator, 'team' => null],
            ['role' => RoleName::Controller, 'team' => $team],
        ]);
        config(['authorization.external_role_sources.handover' => ['Controller', 'Event Coordinator']]);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('At least one Administrator must remain.');

        try {
            $assignments->syncExternal($user, 'handover', [['role' => RoleName::EventCoordinator, 'team' => $team]]);
        } finally {
            $this->assertSame(['Administrator', 'Controller'], $user->fresh()->effectiveRoleNames()->all());
            $this->assertDatabaseCount('role_grants', 2);
            $this->assertDatabaseCount('model_has_roles', 2);
            $this->assertDatabaseCount('audit_logs', 1);
        }
    }

    public function test_source_snapshots_replace_only_that_users_source_roles_across_firs(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $firstTeam = Team::factory()->create();
        $secondTeam = Team::factory()->create();
        $assignments = app(UpdateRoleAssignments::class);
        $old = [['role' => RoleName::EventCoordinator, 'team' => $firstTeam]];
        $assignments->syncExternal($user, 'handover', $old);
        $assignments->syncExternal($otherUser, 'handover', $old);

        $assignments->syncExternal($user, 'handover', [['role' => RoleName::VaccStaff, 'team' => $secondTeam]]);

        $this->assertFalse($user->can(PermissionName::ViewEvents, $firstTeam));
        $this->assertTrue($user->can(PermissionName::ViewEvents, $secondTeam));
        $this->assertFalse($user->can(PermissionName::ManageEvents, $secondTeam));
        $this->assertTrue($otherUser->can(PermissionName::ManageEvents, $firstTeam));
    }

    public function test_repeated_and_duplicate_snapshots_are_idempotent_and_empty_snapshot_restores_pilot(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $assignments = app(UpdateRoleAssignments::class);
        $grant = ['role' => RoleName::Controller, 'team' => $team];
        $assignments->syncExternal($user, 'handover', [$grant, $grant]);

        $assignments->syncExternal($user, 'handover', [$grant]);

        $this->assertDatabaseCount('role_grants', 1);
        $this->assertDatabaseCount('model_has_roles', 1);

        $assignments->syncExternal($user, 'handover', []);

        $this->assertSame(['Pilot'], $user->effectiveRoleNames()->all());
        $this->assertDatabaseCount('model_has_roles', 0);
    }

    #[TestWith(['manual'])]
    #[TestWith(['unknown'])]
    public function test_unconfigured_or_reserved_sources_cannot_change_existing_grants(string $source): void
    {
        $user = User::factory()->create();
        $assignments = app(UpdateRoleAssignments::class);
        $assignments->grant($user, RoleName::Administrator);
        $this->expectException(InvalidArgumentException::class);

        try {
            $assignments->syncExternal($user, $source, []);
        } finally {
            $this->assertTrue($user->isAdministrator());
        }
    }

    public function test_disallowed_external_administrator_grants_do_not_partially_apply_a_snapshot(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $assignments = app(UpdateRoleAssignments::class);
        $assignments->syncExternal($user, 'handover', [['role' => RoleName::Controller, 'team' => $team]]);
        $this->expectException(InvalidArgumentException::class);

        try {
            $assignments->syncExternal($user, 'handover', [
                ['role' => RoleName::EventCoordinator, 'team' => $team],
                ['role' => RoleName::Administrator, 'team' => null],
            ]);
        } finally {
            $this->assertSame(['Controller'], $user->effectiveRoleNames()->all());
            $this->assertFalse($user->isAdministrator());
            $this->assertFalse($user->can(PermissionName::ManageEvents, $team));
        }
    }

    public function test_a_failed_sync_write_rolls_back_both_grants_and_effective_permissions(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $assignments = app(UpdateRoleAssignments::class);
        $assignments->syncExternal($user, 'handover', [['role' => RoleName::Controller, 'team' => $team]]);
        Role::where('name', 'vACC Staff')->firstOrFail()->delete();
        $this->expectException(ModelNotFoundException::class);

        try {
            $assignments->syncExternal($user, 'handover', [
                ['role' => RoleName::EventCoordinator, 'team' => $team],
                ['role' => RoleName::VaccStaff, 'team' => $team],
            ]);
        } finally {
            $this->assertSame(['Controller'], $user->effectiveRoleNames()->all());
            $this->assertFalse($user->can(PermissionName::ManageEvents, $team));
            $this->assertDatabaseCount('role_grants', 1);
            $this->assertDatabaseCount('model_has_roles', 1);
        }
    }
}
