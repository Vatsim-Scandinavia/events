<?php

namespace Tests\Feature;

use App\Actions\Authorization\UpdateRoleAssignments;
use App\Models\Team;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('users.index'))->assertRedirect(route('login'));
    }

    public function test_non_administrators_cannot_browse_users_or_see_the_management_navigation(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('auth.can_manage_users', false));
    }

    public function test_administrators_can_browse_profiles_and_role_sources_without_exposing_credentials(): void
    {
        $administrator = $this->administrator();
        $team = Team::factory()->create(['code' => 'EKDK']);
        $user = User::factory()->create([
            'name_full' => 'Alice Member', 'oauth_access_token' => 'secret-access',
            'oauth_refresh_token' => 'secret-refresh', 'oauth_id' => 'private-provider-id',
        ]);
        config(['authorization.external_role_sources' => ['roster' => ['Controller']]]);
        $assignments = app(UpdateRoleAssignments::class);
        $assignments->grant($user, RoleName::Controller, $team);
        $assignments->syncExternal($user, 'roster', [['role' => RoleName::Controller, 'team' => $team]]);

        $this->actingAs($administrator)->get(route('users.index'))->assertInertia(fn (Assert $page) => $page
            ->component('users/index')->where('auth.can_manage_users', true)
            ->has('users.data', 2)->where('users.data.0.cid', $user->cid)
            ->where('users.data.0.roles', ['Controller'])
            ->where('users.data.0.grants.0.source', 'manual')
            ->where('users.data.0.grants.1.source', 'roster')
            ->where('users.data.0.grants.0.team_id', $team->id)
            ->missing('users.data.0.oauth_access_token')->missing('users.data.0.oauth_refresh_token')
            ->missing('users.data.0.oauth_id')->missing('users.data.0.remember_token')
            ->where('teams.0.code', 'EKDK')->has('roles', 5));
    }

    #[TestWith(['alice'])]
    #[TestWith(['alice@example.test'])]
    #[TestWith(['1234567'])]
    public function test_search_matches_name_email_or_exact_cid(string $search): void
    {
        $administrator = $this->administrator();
        $user = User::factory()->create(['cid' => 1234567, 'name_full' => 'Alice Member', 'email' => 'alice@example.test']);
        User::factory()->create(['name_full' => 'Bob Member', 'email' => 'bob@example.test']);

        $this->actingAs($administrator)->get(route('users.index', ['search' => $search]))
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.cid', $user->cid));
    }

    public function test_role_and_fir_filters_must_match_the_same_assignment(): void
    {
        $administrator = $this->administrator();
        $firstTeam = Team::factory()->create();
        $secondTeam = Team::factory()->create();
        $matching = User::factory()->create();
        $other = User::factory()->create();
        $assignments = app(UpdateRoleAssignments::class);
        $assignments->grant($matching, RoleName::EventCoordinator, $firstTeam);
        $assignments->grant($other, RoleName::Controller, $firstTeam);
        $assignments->grant($other, RoleName::EventCoordinator, $secondTeam);

        $this->actingAs($administrator)->get(route('users.index', ['role' => 'Event Coordinator', 'team_id' => $firstTeam->id]))
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.cid', $matching->cid));
    }

    public function test_pilot_filter_includes_users_with_no_assignments(): void
    {
        $administrator = $this->administrator();
        $pilot = User::factory()->create();

        $this->actingAs($administrator)->get(route('users.index', ['role' => 'Pilot']))
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)
                ->where('users.data.0.cid', $pilot->cid)->where('users.data.0.roles', ['Pilot'])->has('users.data.0.grants', 0));
    }

    public function test_results_are_paginated_in_name_order_and_keep_filters(): void
    {
        $administrator = $this->administrator();
        User::factory()->count(16)->sequence(fn ($sequence): array => [
            'name_full' => sprintf('Member %02d', $sequence->index),
        ])->create();

        $this->actingAs($administrator)->get(route('users.index', ['search' => 'Member', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)
                ->where('users.data.0.name_full', 'Member 15')->where('users.total', 16)
                ->where('users.current_page', 2)->where('filters.search', 'Member')
                ->where('users.prev_page_url', route('users.index', ['search' => 'Member', 'page' => 1])));
    }

    public function test_unmatched_search_returns_an_empty_directory(): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator)->get(route('users.index', ['search' => "' OR 1=1 --"]))
            ->assertInertia(fn (Assert $page) => $page->has('users.data', 0)->where('users.total', 0));
    }

    #[TestWith([['role' => 'invalid'], 'role'])]
    #[TestWith([['team_id' => 999999], 'team_id'])]
    #[TestWith([['search' => ['invalid']], 'search'])]
    #[TestWith([['page' => 0], 'page'])]
    public function test_invalid_filters_return_validation_errors(array $filters, string $field): void
    {
        $this->actingAs($this->administrator())->getJson(route('users.index', $filters))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_eager_loaded_role_names_are_refreshed_after_assignment_changes(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->load('assignedRoles');
        $assignments = app(UpdateRoleAssignments::class);
        $assignments->grant($user, RoleName::Administrator);
        $assignments->grant(User::factory()->create(), RoleName::Administrator);

        $this->assertSame(['Administrator'], $user->effectiveRoleNames()->all());
        $user->load('assignedRoles');
        $assignments->revoke($user, RoleName::Administrator);

        $this->assertSame(['Pilot'], $user->effectiveRoleNames()->all());
    }

    private function administrator(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $administrator = User::factory()->create(['name_full' => 'Zoe Administrator']);
        app(UpdateRoleAssignments::class)->grant($administrator, RoleName::Administrator);

        return $administrator;
    }
}
