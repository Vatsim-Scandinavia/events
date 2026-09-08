<?php

namespace Tests\Feature;

use App\Actions\Authorization\UpdateRoleAssignments;
use App\Models\User;
use App\RoleName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ApplicationSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->app->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');
    }

    #[TestWith(['setup'])]
    #[TestWith(['post-create-project-cmd'])]
    public function test_setup_initializes_a_missing_key_and_roles_for_administrator_bootstrap(string $script): void
    {
        $environmentFile = $this->useEnvironmentFile("APP_KEY=\n");
        config(['app.key' => '']);

        $this->runSetupArtisanCommands($script);

        $this->assertNotEmpty(config('app.key'));
        $this->assertSame('APP_KEY='.config('app.key')."\n", file_get_contents($environmentFile));
        $user = User::factory()->create();
        $this->artisan('authorization:grant-administrator', ['cid' => $user->cid])->assertSuccessful();
        $this->assertTrue($user->isAdministrator());
    }

    public function test_repeating_setup_preserves_the_key_encrypted_tokens_and_role_assignments(): void
    {
        $key = config('app.key');
        $contents = 'APP_KEY='.$key."\n";
        $environmentFile = $this->useEnvironmentFile($contents);
        $this->runSetupArtisanCommands('setup');
        $user = User::factory()->create(['oauth_access_token' => 'existing-access-token']);
        app(UpdateRoleAssignments::class)->grant($user, RoleName::Administrator);

        $this->runSetupArtisanCommands('setup');

        $this->assertSame($key, config('app.key'));
        $this->assertSame($contents, file_get_contents($environmentFile));
        $this->app->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');
        $this->assertSame('existing-access-token', $user->fresh()->oauth_access_token);
        $this->assertTrue($user->fresh()->isAdministrator());
        $this->assertDatabaseCount('role_grants', 1);
        $this->assertDatabaseCount('roles', 5);
        $this->assertDatabaseCount('permissions', 4);
    }

    public function test_a_key_supplied_outside_the_environment_file_is_preserved(): void
    {
        $key = config('app.key');
        $environmentFile = $this->useEnvironmentFile("APP_NAME=Events\n");

        $this->artisan('app:ensure-key')->assertSuccessful();

        $this->assertSame($key, config('app.key'));
        $this->assertSame("APP_NAME=Events\n", file_get_contents($environmentFile));
    }

    public function test_setup_fails_when_a_missing_key_cannot_be_written(): void
    {
        $environmentFile = $this->useEnvironmentFile("APP_NAME=Events\n");
        config(['app.key' => '']);

        $this->artisan('app:ensure-key')->assertFailed();

        $this->assertSame('', config('app.key'));
        $this->assertSame("APP_NAME=Events\n", file_get_contents($environmentFile));
    }

    private function useEnvironmentFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'events-setup-');
        file_put_contents($path, $contents);
        $this->app->useEnvironmentPath(dirname($path))->loadEnvironmentFrom(basename($path));
        $this->beforeApplicationDestroyed(fn () => unlink($path));

        return $path;
    }

    private function runSetupArtisanCommands(string $script): void
    {
        $manifest = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ($manifest['scripts'][$script] as $command) {
            if (str_starts_with($command, '@php artisan ')) {
                $this->artisan(substr($command, strlen('@php artisan ')))->assertSuccessful();
            }
        }
    }
}
