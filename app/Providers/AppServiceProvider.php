<?php

namespace App\Providers;

use App\Clients\DiscordClient;
use App\Models\Event;
use App\Observers\EventObserver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        Event::observe(EventObserver::class);

        // Force HTTPS URLs in production and Codespaces
        if (config('app.env') !== 'local' || env('CODESPACE_NAME')) {
            URL::forceScheme('https');
        }
    }
}
