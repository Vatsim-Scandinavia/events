<?php

namespace Tests\Feature;

use App\Actions\Authorization\UpdateRoleAssignments;
use App\Models\Team;
use App\Models\User;
use App\PermissionName;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class FirManagementTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['get', 'firs.index'])]
    #[TestWith(['post', 'firs.store'])]
    #[TestWith(['put', 'firs.update'])]
    #[TestWith(['delete', 'firs.destroy'])]
    public function test_guests_are_redirected_to_login(string $method, string $route): void
    {
        $fir = Team::factory()->create();

        $this->{$method}(route($route, ['fir' => $fir->id]))->assertRedirect(route('login'));

        $this->assertModelExists($fir);
    }

    #[TestWith(['get', 'firs.index'])]
    #[TestWith(['post', 'firs.store'])]
    #[TestWith(['put', 'firs.update'])]
    #[TestWith(['delete', 'firs.destroy'])]
    public function test_management_endpoints_forbid_non_administrators(string $method, string $route): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $fir = Team::factory()->create(['code' => 'EKDK', 'name' => 'Copenhagen FIR']);
        $coordinator = User::factory()->create();
        app(UpdateRoleAssignments::class)->grant($coordinator, RoleName::EventCoordinator, $fir);

        $this->actingAs($coordinator)->{$method}(route($route, ['fir' => $fir->id]), ['code' => 'ESAA', 'name' => 'Changed FIR'])
            ->assertForbidden();

        $this->assertDatabaseCount('teams', 1);
        $this->assertDatabaseHas('teams', ['id' => $fir->id, 'code' => 'EKDK', 'name' => 'Copenhagen FIR']);
    }

    #[TestWith([RoleName::Administrator, true])]
    #[TestWith([RoleName::EventCoordinator, false])]
    #[TestWith([RoleName::VaccStaff, false])]
    #[TestWith([RoleName::Controller, false])]
    #[TestWith([RoleName::Pilot, false])]
    public function test_only_administrators_can_manage_firs_and_see_the_navigation(RoleName $role, bool $allowed): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $fir = Team::factory()->create();

        if ($role !== RoleName::Pilot) {
            app(UpdateRoleAssignments::class)->grant($user, $role, $role->isGlobal() ? null : $fir);
        }

        $this->assertSame($allowed, $user->can(PermissionName::ManageFirs));
        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.can_manage_firs', $allowed));
    }

    public function test_directory_counts_each_member_once_across_roles_and_sources(): void
    {
        $administrator = $this->administrator();
        $fir = Team::factory()->create(['code' => 'EKDK', 'name' => 'Copenhagen FIR']);
        Team::factory()->create(['code' => 'ESAA']);
        $member = User::factory()->create();
        $assignments = app(UpdateRoleAssignments::class);
        $assignments->grant($member, RoleName::Controller, $fir);
        $assignments->grant($member, RoleName::EventCoordinator, $fir);
        config(['authorization.external_role_sources' => ['roster' => ['Controller']]]);
        $assignments->syncExternal($member, 'roster', [['role' => RoleName::Controller, 'team' => $fir]]);

        $this->actingAs($administrator)->get(route('firs.index'))
            ->assertInertia(fn (Assert $page) => $page->component('firs/index')
                ->has('firs.data', 2)->where('firs.data.0.code', 'EKDK')
                ->where('firs.data.0.name', 'Copenhagen FIR')->where('firs.data.0.members_count', 1)
                ->where('firs.data.1.members_count', 0));
    }

    #[TestWith(['ekdk'])]
    #[TestWith(['copenhagen'])]
    public function test_search_matches_code_or_name_case_insensitively(string $search): void
    {
        $administrator = $this->administrator();
        $fir = Team::factory()->create(['code' => 'EKDK', 'name' => 'Copenhagen FIR']);
        Team::factory()->create(['code' => 'ESAA', 'name' => 'Sweden FIR']);

        $this->actingAs($administrator)->get(route('firs.index', ['search' => $search]))
            ->assertInertia(fn (Assert $page) => $page->has('firs.data', 1)->where('firs.data.0.id', $fir->id));
    }

    public function test_results_are_paginated_in_code_order_and_keep_the_search(): void
    {
        $administrator = $this->administrator();
        Team::factory()->count(16)->sequence(fn ($sequence): array => [
            'code' => 'EKA'.chr(ord('A') + $sequence->index), 'name' => 'Region FIR',
        ])->create();

        $this->actingAs($administrator)->get(route('firs.index', ['search' => 'Region', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('firs.data', 1)
                ->where('firs.data.0.code', 'EKAP')->where('firs.total', 16)->where('firs.current_page', 2)
                ->where('filters.search', 'Region')
                ->where('firs.prev_page_url', route('firs.index', ['search' => 'Region', 'page' => 1])));
    }

    public function test_unmatched_search_returns_an_empty_directory(): void
    {
        $administrator = $this->administrator();
        Team::factory()->create(['code' => 'EKDK', 'name' => 'Copenhagen FIR']);

        $this->actingAs($administrator)->get(route('firs.index', ['search' => "' OR 1=1 --"]))
            ->assertInertia(fn (Assert $page) => $page->has('firs.data', 0)->where('firs.total', 0));
    }

    #[TestWith(['search', ['invalid']])]
    #[TestWith(['page', 0])]
    public function test_invalid_directory_filters_are_rejected(string $field, mixed $value): void
    {
        $this->actingAs($this->administrator())->get(route('firs.index', [$field => $value]))
            ->assertSessionHasErrors($field);
    }

    public function test_administrators_can_create_firs_with_normalized_codes_and_only_intended_attributes(): void
    {
        $this->actingAs($this->administrator())->post(route('firs.store'), [
            'code' => ' ekdk ', 'name' => ' Copenhagen FIR ', 'id' => 9000,
        ])->assertRedirectToRoute('firs.index')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('teams', ['code' => 'EKDK', 'name' => 'Copenhagen FIR']);
        $this->assertDatabaseMissing('teams', ['id' => 9000]);
        $this->assertDatabaseCount('teams', 1);
    }

    public function test_administrators_can_rename_firs_without_changing_member_access(): void
    {
        $administrator = $this->administrator();
        $fir = Team::factory()->create(['code' => 'EKDK', 'name' => 'Old name']);
        $member = User::factory()->create();
        app(UpdateRoleAssignments::class)->grant($member, RoleName::EventCoordinator, $fir);

        $this->actingAs($administrator)->from(route('firs.index'))->put(route('firs.update', $fir), [
            'code' => ' ekdk ', 'name' => 'Copenhagen FIR', 'id' => 9000,
        ])->assertRedirectToRoute('firs.index')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('teams', ['id' => $fir->id, 'code' => 'EKDK', 'name' => 'Copenhagen FIR']);
        $this->assertDatabaseHas('role_grants', ['user_id' => $member->cid, 'team_id' => $fir->id]);
        $this->assertTrue($member->can(PermissionName::ManageEvents, $fir));
    }

    public function test_administrators_can_change_a_fir_code(): void
    {
        $administrator = $this->administrator();
        $fir = Team::factory()->create(['code' => 'ESAA']);

        $this->actingAs($administrator)->from(route('firs.index'))->put(route('firs.update', $fir), [
            'code' => 'ekdk', 'name' => 'Copenhagen FIR',
        ])->assertSessionHasNoErrors()->assertRedirectToRoute('firs.index');

        $this->assertDatabaseHas('teams', ['id' => $fir->id, 'code' => 'EKDK']);
        $this->assertDatabaseMissing('teams', ['code' => 'ESAA']);
    }

    #[TestWith(['post', 'firs.store'])]
    #[TestWith(['put', 'firs.update'])]
    public function test_code_and_name_are_required_for_creating_and_updating_firs(string $method, string $route): void
    {
        $administrator = $this->administrator();
        $fir = Team::factory()->create(['code' => 'EKDK', 'name' => 'Copenhagen FIR']);

        $this->actingAs($administrator)->{$method}(route($route, ['fir' => $fir->id]), [])
            ->assertSessionHasErrors(['code' => 'The FIR code field is required.', 'name' => 'The FIR name field is required.']);

        $this->assertDatabaseCount('teams', 1);
        $this->assertDatabaseHas('teams', ['id' => $fir->id, 'code' => 'EKDK', 'name' => 'Copenhagen FIR']);
    }

    #[TestWith(['code', 'EKD', 'The FIR code must be exactly 4 letters (A-Z).'])]
    #[TestWith(['code', 'EKDKK', 'The FIR code must be exactly 4 letters (A-Z).'])]
    #[TestWith(['code', 'EK D', 'The FIR code must be exactly 4 letters (A-Z).'])]
    #[TestWith(['code', 'EKD1', 'The FIR code must be exactly 4 letters (A-Z).'])]
    #[TestWith(['code', 'EK-D', 'The FIR code must be exactly 4 letters (A-Z).'])]
    #[TestWith(['code', 'ÉKDK', 'The FIR code must be exactly 4 letters (A-Z).'])]
    #[TestWith(['code', ['EKDK'], 'The FIR code field must be a string.'])]
    #[TestWith(['name', ['Copenhagen'], 'The FIR name field must be a string.'])]
    public function test_invalid_fir_details_are_rejected(string $field, mixed $value, string $message): void
    {
        $this->actingAs($this->administrator())->post(route('firs.store'), [
            ...['code' => 'EKDK', 'name' => 'Copenhagen FIR'], $field => $value,
        ])->assertSessionHasErrors([$field => $message]);

        $this->assertDatabaseCount('teams', 0);
    }

    public function test_invalid_code_updates_leave_the_fir_unchanged(): void
    {
        $administrator = $this->administrator();
        $fir = Team::factory()->create(['code' => 'EKDK', 'name' => 'Copenhagen FIR']);

        $this->actingAs($administrator)->put(route('firs.update', $fir), [
            'code' => 'EK-D', 'name' => 'Changed FIR',
        ])->assertSessionHasErrors(['code' => 'The FIR code must be exactly 4 letters (A-Z).']);

        $this->assertDatabaseCount('teams', 1);
        $this->assertDatabaseHas('teams', ['id' => $fir->id, 'code' => 'EKDK', 'name' => 'Copenhagen FIR']);
    }

    public function test_overlong_names_are_rejected(): void
    {
        $this->actingAs($this->administrator())->post(route('firs.store'), [
            'code' => 'EKDK', 'name' => str_repeat('x', 256),
        ])->assertSessionHasErrors(['name' => 'The FIR name field must not be greater than 255 characters.']);

        $this->assertDatabaseCount('teams', 0);
    }

    #[TestWith(['post', 'firs.store'])]
    #[TestWith(['put', 'firs.update'])]
    public function test_duplicate_codes_are_rejected_after_normalization(string $method, string $route): void
    {
        $administrator = $this->administrator();
        Team::factory()->create(['code' => 'EKDK']);
        $fir = Team::factory()->create(['code' => 'ESAA', 'name' => 'Sweden FIR']);

        $this->actingAs($administrator)->{$method}(route($route, ['fir' => $fir->id]), [
            'code' => ' ekdk ', 'name' => 'Duplicate FIR',
        ])->assertSessionHasErrors(['code' => 'A FIR with this code already exists.']);

        $this->assertDatabaseCount('teams', 2);
        $this->assertDatabaseHas('teams', ['id' => $fir->id, 'code' => 'ESAA', 'name' => 'Sweden FIR']);
    }

    public function test_administrators_can_delete_unused_firs_without_affecting_other_firs(): void
    {
        $administrator = $this->administrator();
        $fir = Team::factory()->create();
        $otherFir = Team::factory()->create();
        $member = User::factory()->create();
        app(UpdateRoleAssignments::class)->grant($member, RoleName::Controller, $otherFir);

        $this->actingAs($administrator)->delete(route('firs.destroy', $fir))
            ->assertRedirectToRoute('firs.index')->assertSessionHasNoErrors();

        $this->assertModelMissing($fir);
        $this->assertModelExists($otherFir);
        $this->assertDatabaseHas('role_grants', ['user_id' => $member->cid, 'team_id' => $otherFir->id]);
        $this->assertTrue($administrator->can(PermissionName::ManageFirs));
    }

    #[TestWith(['manual'])]
    #[TestWith(['roster'])]
    public function test_firs_with_role_assignments_cannot_be_deleted(string $source): void
    {
        $administrator = $this->administrator();
        $fir = Team::factory()->create();
        $member = User::factory()->create();
        $assignments = app(UpdateRoleAssignments::class);

        if ($source === 'manual') {
            $assignments->grant($member, RoleName::EventCoordinator, $fir);
        } else {
            config(['authorization.external_role_sources' => ['roster' => ['Event Coordinator']]]);
            $assignments->syncExternal($member, 'roster', [['role' => RoleName::EventCoordinator, 'team' => $fir]]);
        }

        $this->actingAs($administrator)->delete(route('firs.destroy', $fir))
            ->assertSessionHasErrors(['fir' => 'Remove all role assignments from this FIR before deleting it, including assignments from external sources.']);

        $this->assertModelExists($fir);
        $this->assertDatabaseHas('role_grants', ['user_id' => $member->cid, 'team_id' => $fir->id, 'source' => $source]);
        $this->assertTrue($member->can(PermissionName::ManageEvents, $fir));
    }

    #[TestWith(['put', 'firs.update'])]
    #[TestWith(['delete', 'firs.destroy'])]
    public function test_unknown_firs_return_not_found(string $method, string $route): void
    {
        $this->actingAs($this->administrator())->{$method}(route($route, 99999), [
            'code' => 'EKDK', 'name' => 'Copenhagen FIR',
        ])->assertNotFound();

        $this->assertDatabaseCount('teams', 0);
    }

    private function administrator(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $administrator = User::factory()->create();
        app(UpdateRoleAssignments::class)->grant($administrator, RoleName::Administrator);

        return $administrator;
    }
}
