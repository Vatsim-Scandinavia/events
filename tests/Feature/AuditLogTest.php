<?php

namespace Tests\Feature;

use App\Actions\Auth\SyncOAuthUser;
use App\Actions\Authorization\UpdateRoleAssignments;
use App\Actions\RecordAudit;
use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use App\PermissionName;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_read_the_audit_log(): void
    {
        $this->get(route('audit-logs.index'))->assertRedirectToRoute('login');
    }

    #[TestWith([RoleName::Administrator, true])]
    #[TestWith([RoleName::EventCoordinator, false])]
    #[TestWith([RoleName::VaccStaff, false])]
    #[TestWith([RoleName::Controller, false])]
    #[TestWith([RoleName::Pilot, false])]
    public function test_only_administrators_can_read_history_and_see_navigation(RoleName $role, bool $allowed): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        if ($role !== RoleName::Pilot) {
            app(UpdateRoleAssignments::class)->grant($user, $role, $role->isGlobal() ? null : Team::factory()->create());
        }

        $this->assertSame($allowed, $user->can(PermissionName::ViewAuditLogs));
        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.can_view_audit_logs', $allowed));
    }

    public function test_non_administrators_get_403_even_with_filters(): void
    {
        $this->actingAs(User::factory()->create())->get(route('audit-logs.index', ['subject_type' => 'user']))
            ->assertForbidden();
    }

    public function test_fir_changes_record_the_authenticated_actor_and_only_changed_values(): void
    {
        $administrator = $this->administrator();
        $this->travelTo('2026-09-10 12:00:00');
        $this->actingAs($administrator)->post(route('firs.store'), [
            'code' => ' ekdk ', 'name' => 'Copenhagen FIR', 'actor_cid' => 7654321,
        ])->assertRedirectToRoute('firs.index');
        $fir = Team::sole();
        $created = AuditLog::where('subject_type', 'fir')->sole();

        $this->assertSame($administrator->cid, $created->actor_cid);
        $this->assertSame($administrator->name_full, $created->actor_name);
        $this->assertSame('created', $created->event);
        $this->assertSame([], $created->old_values);
        $this->assertSame(['code' => 'EKDK', 'name' => 'Copenhagen FIR'], $created->new_values);
        $this->assertTrue($created->created_at->equalTo(now()));

        $this->put(route('firs.update', $fir), ['code' => 'EKDK', 'name' => 'Renamed FIR'])->assertSessionHasNoErrors();
        $updated = AuditLog::where('subject_type', 'fir')->where('event', 'updated')->sole();
        $this->assertSame(['name' => 'Copenhagen FIR'], $updated->old_values);
        $this->assertSame(['name' => 'Renamed FIR'], $updated->new_values);

        $this->put(route('firs.update', $fir), ['code' => 'EKDK', 'name' => 'Renamed FIR'])->assertSessionHasNoErrors();
        $this->assertSame(2, AuditLog::where('subject_type', 'fir')->count());
    }

    public function test_deleted_records_and_actor_names_remain_in_history(): void
    {
        $administrator = $this->administrator();
        $fir = Team::factory()->create(['code' => 'EKDK', 'name' => 'Copenhagen FIR']);

        $this->actingAs($administrator)->delete(route('firs.destroy', $fir))->assertRedirectToRoute('firs.index');
        $administrator->delete();

        $log = AuditLog::where('subject_type', 'fir')->sole();
        $this->assertModelMissing($fir);
        $this->assertSame($administrator->cid, $log->actor_cid);
        $this->assertSame($administrator->name_full, $log->actor_name);
        $this->assertSame('EKDK — Copenhagen FIR', $log->subject_label);
        $this->assertSame('deleted', $log->event);
        $this->assertSame(['code' => 'EKDK', 'name' => 'Copenhagen FIR'], $log->old_values);
        $this->assertSame([], $log->new_values);
    }

    public function test_rejected_fir_changes_do_not_create_history(): void
    {
        $administrator = $this->administrator();
        $fir = Team::factory()->create();
        app(UpdateRoleAssignments::class)->grant(User::factory()->create(), RoleName::Controller, $fir);
        $count = AuditLog::count();

        $this->actingAs($administrator)->put(route('firs.update', $fir), ['code' => 'INVALID'])->assertSessionHasErrors();
        $this->delete(route('firs.destroy', $fir))->assertSessionHasErrors('fir');
        $this->actingAs(User::factory()->create())->post(route('firs.store'), ['code' => 'EKDK', 'name' => 'Copenhagen FIR'])->assertForbidden();

        $this->assertDatabaseCount('audit_logs', $count);
    }

    public function test_role_grants_and_revocations_record_source_scope_and_subject_without_duplicate_entries(): void
    {
        $administrator = $this->administrator();
        $user = User::factory()->create();
        $fir = Team::factory()->create(['code' => 'EKDK']);
        $payload = ['role' => 'Controller', 'team_id' => $fir->id];
        $assignment = ['role' => 'Controller', 'fir_id' => $fir->id, 'fir' => 'EKDK', 'source' => 'manual'];

        $this->actingAs($administrator)->postJson(route('users.roles.store', $user), $payload)->assertNoContent();
        $this->postJson(route('users.roles.store', $user), $payload)->assertNoContent();

        $log = AuditLog::where('subject_id', $user->cid)->sole();
        $this->assertSame($administrator->cid, $log->actor_cid);
        $this->assertSame('roles_updated', $log->event);
        $this->assertSame('manual', $log->source);
        $this->assertSame(['roles' => []], $log->old_values);
        $this->assertSame(['roles' => [$assignment]], $log->new_values);

        $this->deleteJson(route('users.roles.destroy', $user), $payload)->assertNoContent();
        $this->deleteJson(route('users.roles.destroy', $user), $payload)->assertNoContent();
        $revoked = AuditLog::where('subject_id', $user->cid)->orderByDesc('id')->firstOrFail();
        $this->assertSame(['roles' => [$assignment]], $revoked->old_values);
        $this->assertSame(['roles' => []], $revoked->new_values);
        $this->assertSame(2, AuditLog::where('subject_id', $user->cid)->count());
    }

    public function test_external_sync_retains_provenance_and_ignores_unchanged_snapshots(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['authorization.external_role_sources' => ['roster' => ['Controller']]]);
        $user = User::factory()->create();
        $fir = Team::factory()->create(['code' => 'EKDK']);
        $assignments = app(UpdateRoleAssignments::class);
        $assignments->grant($user, RoleName::Controller, $fir);

        $assignments->syncExternal($user, 'roster', [['role' => RoleName::Controller, 'team' => $fir]]);
        $assignments->syncExternal($user, 'roster', [['role' => RoleName::Controller, 'team' => $fir]]);

        $log = AuditLog::where('source', 'roster')->sole();
        $this->assertNull($log->actor_cid);
        $this->assertSame(['manual', 'roster'], array_column($log->new_values['roles'], 'source'));

        $assignments->syncExternal($user, 'roster', []);
        $removed = AuditLog::where('source', 'roster')->latest('id')->firstOrFail();
        $this->assertSame(['manual'], array_column($removed->new_values['roles'], 'source'));
        $this->assertSame(2, AuditLog::where('source', 'roster')->count());
        $this->assertTrue($user->roleGrants()->where('source', 'manual')->exists());
    }

    public function test_profile_sync_records_verified_identity_and_changes_without_tokens_or_token_only_noise(): void
    {
        $identity = (new SocialiteUser)->map([
            'id' => '1234567', 'name' => 'Test Controller', 'email' => 'controller@example.com',
            'controller_rating' => 5, 'division' => 'EUD', 'subdivision' => 'SCA',
        ])->setToken('secret-access')->setRefreshToken('secret-refresh')->setExpiresIn(3600);
        $sync = app(SyncOAuthUser::class);

        $user = $sync->handle('vatsim', $identity);
        $log = AuditLog::sole();
        $this->assertSame($user->cid, $log->actor_cid);
        $this->assertSame('oauth:vatsim', $log->source);
        $this->assertSame('created', $log->event);
        $this->assertSame([
            'name_full' => 'Test Controller', 'email' => 'controller@example.com', 'controller_rating' => 5,
            'division' => 'EUD', 'subdivision' => 'SCA', 'oauth_provider' => 'vatsim',
        ], $log->new_values);

        $identity->setToken('rotated-access')->setRefreshToken('rotated-refresh');
        $sync->handle('vatsim', $identity);
        $this->assertDatabaseCount('audit_logs', 1);

        $identity->map([...$identity->attributes, 'name' => 'Updated Controller']);
        $sync->handle('vatsim', $identity);
        $updated = AuditLog::latest('id')->firstOrFail();
        $this->assertSame(['name_full' => 'Test Controller'], $updated->old_values);
        $this->assertSame(['name_full' => 'Updated Controller'], $updated->new_values);
        $this->assertStringNotContainsString('secret', AuditLog::all()->toJson());
        $this->assertStringNotContainsString('rotated', AuditLog::all()->toJson());
    }

    public function test_recorder_allowlists_fields_even_if_a_caller_passes_secrets(): void
    {
        $user = User::factory()->create();

        app(RecordAudit::class)->handle($user, 'updated', ['name_full' => 'Before', 'oauth_access_token' => 'old-secret'], [
            'name_full' => 'After', 'oauth_access_token' => 'new-secret', 'remember_token' => 'remember-secret', 'password' => 'password-secret',
        ]);

        $this->assertSame(['name_full' => 'Before'], AuditLog::sole()->old_values);
        $this->assertSame(['name_full' => 'After'], AuditLog::sole()->new_values);
    }

    public function test_failed_profile_auditing_rolls_back_account_creation(): void
    {
        $identity = (new SocialiteUser)->map([
            'id' => '1234567', 'name' => 'Test Controller', 'email' => 'controller@example.com',
            'controller_rating' => 5,
        ])->setToken('secret-access');
        Event::listen('eloquent.creating: '.AuditLog::class, fn () => throw new RuntimeException('Audit storage unavailable'));

        try {
            app(SyncOAuthUser::class)->handle('vatsim', $identity);
            $this->fail('An audit failure must abort profile persistence.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit storage unavailable', $exception->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.AuditLog::class);
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    #[TestWith(['post', 'firs.store'])]
    #[TestWith(['put', 'firs.update'])]
    #[TestWith(['delete', 'firs.destroy'])]
    public function test_audit_write_failure_rolls_back_fir_changes(string $method, string $route): void
    {
        $administrator = $this->administrator();
        $fir = Team::factory()->create(['code' => 'ESAA', 'name' => 'Sweden FIR']);
        $count = AuditLog::count();
        Event::listen('eloquent.creating: '.AuditLog::class, fn () => throw new RuntimeException('Audit storage unavailable'));

        try {
            $this->actingAs($administrator)->{$method}(route($route, ['fir' => $fir->id]), ['code' => 'EKDK', 'name' => 'Changed'])->assertServerError();
        } finally {
            Event::forget('eloquent.creating: '.AuditLog::class);
        }

        $this->assertDatabaseCount('teams', 1);
        $this->assertDatabaseHas('teams', ['id' => $fir->id, 'code' => 'ESAA', 'name' => 'Sweden FIR']);
        $this->assertDatabaseCount('audit_logs', $count);
    }

    public function test_failed_auditing_rolls_back_role_grants_and_their_permission_projection(): void
    {
        $administrator = $this->administrator();
        $user = User::factory()->create();
        $count = AuditLog::count();
        Event::listen('eloquent.creating: '.AuditLog::class, fn () => throw new RuntimeException('Audit storage unavailable'));

        try {
            $this->actingAs($administrator)->postJson(route('users.roles.store', $user), ['role' => 'Administrator'])->assertServerError();
        } finally {
            Event::forget('eloquent.creating: '.AuditLog::class);
        }

        $this->assertFalse($user->isAdministrator());
        $this->assertDatabaseMissing('role_grants', ['user_id' => $user->cid]);
        $this->assertDatabaseCount('audit_logs', $count);
    }

    public function test_rolled_back_operations_leave_no_audit_entries(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();

        DB::beginTransaction();
        app(UpdateRoleAssignments::class)->grant($user, RoleName::Administrator);
        DB::rollBack();

        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertFalse($user->isAdministrator());
    }

    public function test_administrators_can_read_changes_in_stable_newest_first_order_with_pagination(): void
    {
        $administrator = $this->administrator();
        $this->travelTo('2026-09-10 12:00:00');
        $logs = AuditLog::factory()->count(21)->create();

        $this->actingAs($administrator)->get(route('audit-logs.index', ['subject_type' => 'fir']))
            ->assertInertia(fn (Assert $page) => $page->component('audit-logs/index')
                ->has('logs.data', 20)->where('logs.total', 21)
                ->where('logs.data.0.id', $logs->last()->id)
                ->where('logs.data.0.old_values.name', 'Old name')
                ->where('logs.data.0.new_values.name', 'Copenhagen FIR'));

        $this->get(route('audit-logs.index', ['subject_type' => 'fir', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('logs.data', 1)
                ->where('logs.data.0.id', $logs->first()->id)
                ->where('filters.subject_type', 'fir')
                ->where('logs.prev_page_url', route('audit-logs.index', ['subject_type' => 'fir', 'page' => 1])));
    }

    #[TestWith(['search', 'operator'])]
    #[TestWith(['search', '7654321'])]
    #[TestWith(['search', 'ekdk'])]
    #[TestWith(['search', '987654'])]
    #[TestWith(['search', 'roster'])]
    #[TestWith(['event', 'deleted'])]
    public function test_filters_find_actor_record_source_and_action(string $filter, string $value): void
    {
        $administrator = $this->administrator();
        $target = AuditLog::factory()->create([
            'actor_cid' => 7654321, 'actor_name' => 'Operator', 'subject_id' => 987654,
            'subject_label' => 'EKDK — Copenhagen FIR', 'source' => 'roster', 'event' => 'deleted',
        ]);
        AuditLog::factory()->create(['subject_id' => 1, 'subject_label' => 'ESAA — Sweden FIR']);

        $this->actingAs($administrator)->get(route('audit-logs.index', [$filter => $value]))
            ->assertInertia(fn (Assert $page) => $page->has('logs.data', 1)->where('logs.data.0.id', $target->id));
    }

    public function test_date_filters_include_the_whole_utc_day_and_combine_with_other_filters(): void
    {
        $administrator = $this->administrator();
        AuditLog::factory()->create(['created_at' => '2026-09-10 00:00:00']);
        $last = AuditLog::factory()->create(['created_at' => '2026-09-10 23:59:59']);
        AuditLog::factory()->create(['created_at' => '2026-09-09 23:59:59']);
        AuditLog::factory()->create(['created_at' => '2026-09-11 00:00:00']);
        AuditLog::factory()->create(['created_at' => '2026-09-10 12:00:00', 'event' => 'deleted']);

        $this->actingAs($administrator)->get(route('audit-logs.index', [
            'from' => '2026-09-10', 'to' => '2026-09-10', 'subject_type' => 'fir', 'event' => 'updated', 'search' => 'EKDK',
        ]))->assertInertia(fn (Assert $page) => $page->has('logs.data', 2)->where('logs.data.0.id', $last->id));
    }

    public function test_unmatched_or_injection_searches_return_empty_results(): void
    {
        $administrator = $this->administrator();
        AuditLog::factory()->create();

        $this->actingAs($administrator)->get(route('audit-logs.index', ['search' => "' OR 1=1 --"]))
            ->assertInertia(fn (Assert $page) => $page->has('logs.data', 0));
    }

    #[TestWith(['search', ['invalid']])]
    #[TestWith(['subject_type', 'secret'])]
    #[TestWith(['event', 'invalid'])]
    #[TestWith(['from', '2026-02-30'])]
    #[TestWith(['to', 'yesterday'])]
    #[TestWith(['page', 0])]
    public function test_invalid_filters_are_rejected(string $field, mixed $value): void
    {
        $this->actingAs($this->administrator())->get(route('audit-logs.index', [$field => $value]))
            ->assertSessionHasErrors($field);
    }

    public function test_reversed_date_ranges_are_rejected(): void
    {
        $this->actingAs($this->administrator())->get(route('audit-logs.index', ['from' => '2026-09-10', 'to' => '2026-09-09']))
            ->assertSessionHasErrors('to');
    }

    private function administrator(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $administrator = User::factory()->create();
        app(UpdateRoleAssignments::class)->grant($administrator, RoleName::Administrator);

        return $administrator;
    }
}
