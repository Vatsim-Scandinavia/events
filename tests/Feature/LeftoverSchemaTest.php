<?php

namespace Tests\Feature;

use App\Auth\Roles;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeftoverSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_10_23_193145_drop_leftover_password_and_role_tables.php';

    public function test_password_and_old_role_tables_are_gone(): void
    {
        $this->assertFalse(Schema::hasColumns('users', ['password']));
        $this->assertFalse(Schema::hasColumn('users', 'email_verified_at'));
        $this->assertFalse(Schema::hasColumn('users', 'remember_token'));
        $this->assertFalse(Schema::hasTable('password_reset_tokens'));
        $this->assertFalse(Schema::hasTable('model_has_roles'));
        $this->assertFalse(Schema::hasTable('model_has_permissions'));
    }

    public function test_the_migration_can_be_rolled_back_and_run_again(): void
    {
        $migration = require database_path(self::MIGRATION);

        $migration->down();
        $this->assertTrue(Schema::hasColumns('users', ['password', 'email_verified_at', 'remember_token']));
        $this->assertTrue(Schema::hasTable('model_has_roles'));

        $migration->up();
        $this->assertFalse(Schema::hasColumn('users', 'password'));
        $this->assertFalse(Schema::hasTable('model_has_roles'));
    }

    public function test_signing_out_and_deleting_roles_still_work(): void
    {
        $user = User::factory()->staff()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect();
        $this->assertGuest();

        // The permission package detaches a role's holders when it's deleted; ours live in role_assignments.
        $role = Role::create(['name' => 'Temporary']);
        $user->assignRole($role);
        $role->delete();

        $this->assertSame([Roles::EVENT_STAFF], $user->fresh()->roles->pluck('name')->all());
    }
}
