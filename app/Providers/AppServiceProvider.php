<?php

namespace App\Providers;

use App\Clients\DiscordClient;
use App\Models\Event;
use App\Observers\EventObserver;
use App\Socialite\VatsimProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Config keys that must be set for the app to function correctly.
     * Keyed by config path => human-readable env var name.
     */
    private const REQUIRED_CONFIG = [
        'services.vatsim.client_id'     => 'OAUTH_CLIENT_ID',
        'services.vatsim.client_secret' => 'OAUTH_CLIENT_SECRET',
        'services.vatsim.base_url'      => 'OAUTH_BASE_URL',
        'services.vatsim.redirect'      => 'OAUTH_REDIRECT_URL',
        'services.discord.guild_id'     => 'DISCORD_GUILD_ID',
        'services.discord.bot_api_url'  => 'DISCORD_API_URL',
        'services.discord.bot_api_token'=> 'DISCORD_API_TOKEN',
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DiscordClient::class, function () {
            return new DiscordClient();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::observe(EventObserver::class);

        Socialite::extend('vatsim', function () {
            $config = config('services.vatsim');
            return Socialite::buildProvider(VatsimProvider::class, $config);
        });

        // Force HTTPS URLs in production and Codespaces
        if (config('app.env') !== 'local' || env('CODESPACE_NAME')) {
            URL::forceScheme('https');
        }

        // Fail fast if any critical env vars are missing.
        // Skip during tests and interactive artisan commands (migrate, key:generate, etc.)
        // so developers can still bootstrap a fresh environment.
        if (! $this->app->runningUnitTests() && ! $this->app->runningInConsole()) {
            $this->validateRequiredConfig();
        }
    }

    /**
     * Throw a RuntimeException listing every missing required config value.
     */
    public function validateRequiredConfig(): void
    {
        $missing = [];

        foreach (self::REQUIRED_CONFIG as $key => $envVar) {
            if (empty(config($key))) {
                $missing[] = $envVar;
            }
        }

        if (! empty($missing)) {
            throw new \RuntimeException(
                'Missing required environment variable(s): ' . implode(', ', $missing)
            );
        }
    }
}

