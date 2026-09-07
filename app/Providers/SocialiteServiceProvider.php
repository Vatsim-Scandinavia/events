<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\SocialiteManager;
use Laravel\Socialite\Two\AbstractProvider;

class SocialiteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach (config('auth.oauth_providers') as $name => $provider) {
            Socialite::extend($name, fn (): AbstractProvider => app(SocialiteManager::class)->buildProvider($provider['driver'], [
                ...config('services.'.$name),
                'guzzle' => ['connect_timeout' => 5, 'timeout' => 15, 'allow_redirects' => false],
            ]),
            );
        }
    }
}
