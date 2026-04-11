<?php

namespace App\Providers;

use App\Clients\DiscordClient;
use App\Socialite\VatsimProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;

class AppServiceProvider extends ServiceProvider
{
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
        Socialite::extend('vatsim', function () {
            $config = config('services.vatsim');
            return Socialite::buildProvider(VatsimProvider::class, $config);
        });

        // Force HTTPS URLs in production and Codespaces
        if (config('app.env') !== 'local' || env('CODESPACE_NAME')) {
            URL::forceScheme('https');
        }
    }
}
