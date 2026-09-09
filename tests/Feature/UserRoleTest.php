<?php

namespace Tests\Feature;

use App\Actions\Authorization\UpdateRoleAssignments;
use App\Models\Team;
use App\Models\User;
use App\PermissionName;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_administrators_can_grant_and_revoke_fir_roles_by_cid(): void
    {
        $administrator = User::factory()->create();
        $user = User::factory()->create();
        $team = Team::factory()->create();
        app(UpdateRoleAssignments::class)->grant($administrator, RoleName::Administrator);
        $payload = ['role' => 'Event Coordinator', 'team_id' => $team->id];

        $this->actingAs($administrator)->postJson(route('users.roles.store', $user), $payload)->assertNoContent();

        $this->assertTrue($user->can(PermissionName::ManageEvents, $team));
        $this->assertDatabaseHas('role_grants', ['user_id' => $user->cid, 'team_id' => $team->id, 'source' => 'manual']);

        $this->deleteJson(route('users.roles.destroy', $user), $payload)->assertNoContent();

        $this->assertFalse($user->can(PermissionName::ManageEvents, $team));
        $this->assertSame(['Pilot'], $user->effectiveRoleNames()->all());
    }

    public function test_administrators_can_grant_global_administrator_roles_without_a_fir(): void
    {
        $administrator = User::factory()->create();
        $user = User::factory()->create();
        app(UpdateRoleAssignments::class)->grant($administrator, RoleName::Administrator);

        $this->actingAs($administrator)->postJson(route('users.roles.store', $user), ['role' => 'Administrator'])->assertNoContent();

        $this->assertTrue($user->can(PermissionName::ManageRoles));
        $this->assertDatabaseHas('role_grants', ['user_id' => $user->cid, 'team_id' => null, 'scope_id' => 0]);
    }

    public function test_the_final_administrator_cannot_revoke_their_own_role(): void
    {
        $administrator = User::factory()->create();
        app(UpdateRoleAssignments::class)->grant($administrator, RoleName::Administrator);

        $this->actingAs($administrator)
            ->deleteJson(route('users.roles.destroy', $administrator), ['role' => 'Administrator'])
            ->assertUnprocessable()
            ->assertInvalid(['role' => 'At least one Administrator must remain.']);

        $this->assertTrue($administrator->fresh()->isAdministrator());
        $this->assertDatabaseCount('role_grants', 1);
        $this->assertDatabaseCount('model_has_roles', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->get(route('users.index'))->assertOk();
    }

    public function test_an_administrator_can_revoke_their_own_role_when_another_administrator_remains(): void
    {
        $administrator = User::factory()->create();
        $otherAdministrator = User::factory()->create();
        $assignments = app(UpdateRoleAssignments::class);
        $assignments->grant($administrator, RoleName::Administrator);
        $assignments->grant($otherAdministrator, RoleName::Administrator);

        $this->actingAs($administrator)
            ->deleteJson(route('users.roles.destroy', $administrator), ['role' => 'Administrator'])
            ->assertNoContent();

        $this->assertFalse($administrator->fresh()->isAdministrator());
        $this->assertTrue($otherAdministrator->fresh()->isAdministrator());
        $this->assertDatabaseMissing('role_grants', ['user_id' => $administrator->cid]);
        $this->assertDatabaseCount('model_has_roles', 1);
        $this->assertDatabaseCount('audit_logs', 3);
    }

    #[TestWith([null])]
    #[TestWith([RoleName::EventCoordinator])]
    #[TestWith([RoleName::VaccStaff])]
    #[TestWith([RoleName::Controller])]
    public function test_non_administrators_cannot_assign_or_revoke_roles_even_in_their_own_fir(?RoleName $role): void
    {
        $actor = User::factory()->create();
        $user = User::factory()->create();
        $team = Team::factory()->create();
        if ($role !== null) {
            app(UpdateRoleAssignments::class)->grant($actor, $role, $team);
        }
        app(UpdateRoleAssignments::class)->grant($user, RoleName::Controller, $team);

        $this->actingAs($actor)->postJson(route('users.roles.store', $actor), ['role' => 'Administrator'])->assertForbidden();
        $this->deleteJson(route('users.roles.destroy', $user), ['role' => 'Controller', 'team_id' => $team->id])->assertForbidden();

        $this->assertFalse($actor->isAdministrator());
        $this->assertSame(['Controller'], $user->effectiveRoleNames()->all());
    }

    public function test_guests_cannot_assign_or_revoke_roles(): void
    {
        $user = User::factory()->create();

        $this->postJson(route('users.roles.store', $user), ['role' => 'Administrator'])->assertUnauthorized();
        $this->deleteJson(route('users.roles.destroy', $user), ['role' => 'Administrator'])->assertUnauthorized();

        $this->assertDatabaseCount('role_grants', 0);
    }

    #[TestWith([[], 'role'])]
    #[TestWith([['role' => 'Unknown'], 'role'])]
    #[TestWith([['role' => 'Pilot'], 'role'])]
    #[TestWith([['role' => 'Controller'], 'team_id'])]
    #[TestWith([['role' => 'vACC Staff', 'team_id' => 999999], 'team_id'])]
    #[TestWith([['role' => 'Event Coordinator', 'team_id' => 'bad-id'], 'team_id'])]
    #[TestWith([['role' => 'Administrator', 'team_id' => 1], 'team_id'])]
    #[TestWith([['role' => 'Administrator', 'source' => 'handover'], 'source'])]
    public function test_invalid_role_assignments_return_validation_errors_without_grants(array $payload, string $field): void
    {
        $administrator = User::factory()->create();
        $user = User::factory()->create();
        app(UpdateRoleAssignments::class)->grant($administrator, RoleName::Administrator);

        $this->actingAs($administrator)->postJson(route('users.roles.store', $user), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertFalse($user->roleGrants()->exists());
        $this->assertSame(['Pilot'], $user->effectiveRoleNames()->all());
    }
}
