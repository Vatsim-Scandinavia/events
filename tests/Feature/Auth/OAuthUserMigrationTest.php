<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class OAuthUserMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_database_can_be_rolled_back_and_migrated_again(): void
    {
        $migration = $this->migration();

        $migration->down();
        $migration->up();

        $user = User::factory()->create(['cid' => 1234567]);
        $this->assertSame(1234567, $user->fresh()->getAuthIdentifier());
        $this->assertSame(['cid'], Schema::getIndexes('users')[0]['columns']);
    }

    public function test_migration_refuses_to_treat_legacy_generated_ids_as_cids(): void
    {
        $migration = $this->migration();
        $migration->down();
        DB::table('users')->insert(['id' => 1, 'name' => 'Legacy User', 'email' => 'legacy@example.com', 'password' => 'hash']);

        try {
            $migration->up();
            $this->fail('Legacy accounts must be mapped explicitly.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Existing local accounts must be mapped to verified VATSIM CIDs before this migration can run.', $exception->getMessage());
        }

        $this->assertDatabaseHas('users', ['id' => 1, 'name' => 'Legacy User']);
        $this->assertTrue(Schema::hasColumn('users', 'id'));
    }

    public function test_rollback_does_not_discard_oauth_accounts(): void
    {
        $user = User::factory()->create();

        try {
            $this->migration()->down();
            $this->fail('A populated OAuth table cannot be rolled back automatically.');
        } catch (RuntimeException $exception) {
            $this->assertSame('OAuth accounts cannot be converted back to local password accounts automatically.', $exception->getMessage());
        }

        $this->assertModelExists($user);
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_09_07_201930_prepare_users_for_oauth_authentication.php');
    }
}
