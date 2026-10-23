<?php

namespace Tests\Feature;

use App\Auth\Roles;
use App\Enums\AssignmentSource;
use App\Models\Fir;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleAssignmentUniquenessTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_10_23_194824_make_role_assignments_unique.php';

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

    public function test_duplicates_already_in_the_table_are_merged_keeping_any_given_by_hand(): void
    {
        $migration = require database_path(self::MIGRATION);
        $migration->down();

        $user = User::factory()->create();
        $controller = Role::findByName(Roles::CONTROLLER)->id;
        $staff = Role::findByName(Roles::EVENT_STAFF)->id;
        $copenhagen = Fir::factory()->create()->id;
        $row = fn (int $role, ?int $fir, string $source) => ['user_id' => $user->id, 'role_id' => $role, 'fir_id' => $fir, 'source' => $source, 'created_at' => now(), 'updated_at' => now()];

        DB::table('role_assignments')->insert([
            $row($controller, null, 'automatic'),
            $row($controller, null, 'manual'),
            $row($staff, $copenhagen, 'manual'),
            $row($staff, $copenhagen, 'manual'),
            $row($staff, null, 'manual'),
        ]);

        $migration->up();

        $this->assertSame(3, DB::table('role_assignments')->count());
        $this->assertSame(AssignmentSource::Manual, $user->roleAssignments()->where('role_id', $controller)->sole()->source);
        $this->assertSame(1, $user->roleAssignments()->where('role_id', $staff)->where('fir_id', $copenhagen)->count());
    }
}
