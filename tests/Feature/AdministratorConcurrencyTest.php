<?php

namespace Tests\Feature;

use App\Actions\Authorization\UpdateRoleAssignments;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AdministratorConcurrencyTest extends TestCase
{
    #[TestWith(['manual'])]
    #[TestWith(['handover'])]
    public function test_concurrent_removals_leave_one_effective_administrator(string $source): void
    {
        $database = tempnam(sys_get_temp_dir(), 'events-administrator-');
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => $database,
            'database.connections.sqlite.busy_timeout' => 5000,
            'authorization.external_role_sources.handover' => ['Administrator'],
        ]);
        DB::purge('sqlite');
        $this->beforeApplicationDestroyed(function () use ($database): void {
            DB::disconnect('sqlite');
            unlink($database);
        });
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true, '--no-interaction' => true])->assertSuccessful();
        $this->seed(RolesAndPermissionsSeeder::class);
        $firstAdministrator = User::factory()->create();
        $secondAdministrator = User::factory()->create();
        $assignments = app(UpdateRoleAssignments::class);
        $assignments->grant($firstAdministrator, RoleName::Administrator);
        if ($source === 'manual') {
            $assignments->grant($secondAdministrator, RoleName::Administrator);
        } else {
            $assignments->syncExternal($secondAdministrator, $source, [['role' => RoleName::Administrator, 'team' => null]]);
        }
        $process = $this->removalProcess($database, $secondAdministrator, $source);

        DB::beginTransaction();
        try {
            $assignments->revoke($firstAdministrator, RoleName::Administrator);
            $process->start();
            $this->assertTrue($process->waitUntil(fn (string $type, string $output): bool => str_contains($output, "started\n")), $process->getErrorOutput());
            usleep(100_000);
            $this->assertTrue($process->isRunning(), $process->getErrorOutput());
            DB::commit();
            $process->wait();

            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $this->assertSame("started\nAt least one Administrator must remain.\n", $process->getOutput());
            $this->assertFalse($firstAdministrator->fresh()->isAdministrator());
            $this->assertTrue($secondAdministrator->fresh()->isAdministrator());
            $this->assertDatabaseCount('role_grants', 1);
            $this->assertDatabaseHas('role_grants', ['user_id' => $secondAdministrator->cid, 'source' => $source]);
            $this->assertDatabaseCount('model_has_roles', 1);
            $this->assertDatabaseCount('audit_logs', 3);
        } finally {
            $process->stop();
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }

    private function removalProcess(string $database, User $administrator, string $source): Process
    {
        return new Process([PHP_BINARY, '-r', <<<'PHP'
            require 'vendor/autoload.php';
            $app = require 'bootstrap/app.php';
            $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            config([
                'database.connections.sqlite.busy_timeout' => 5000,
                'authorization.external_role_sources.handover' => ['Administrator'],
            ]);
            $user = App\Models\User::findOrFail((int) $argv[1]);
            Illuminate\Support\Facades\Event::listen(
                Illuminate\Database\Events\TransactionBeginning::class,
                function (): void {
                    fwrite(STDOUT, "started\n");
                    fflush(STDOUT);
                },
            );
            try {
                $assignments = app(App\Actions\Authorization\UpdateRoleAssignments::class);
                if ($argv[2] === 'manual') {
                    $assignments->revoke($user, App\RoleName::Administrator);
                } else {
                    $assignments->syncExternal($user, $argv[2], []);
                }
                fwrite(STDOUT, "removed\n");
            } catch (Illuminate\Validation\ValidationException $exception) {
                fwrite(STDOUT, $exception->errors()['role'][0]."\n");
            }
            PHP, (string) $administrator->cid, $source], base_path(), [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $database,
            'DB_URL' => '',
            'CACHE_STORE' => 'array',
        ], timeout: 10);
    }
}
