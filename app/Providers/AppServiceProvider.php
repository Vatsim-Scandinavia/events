<?php

namespace App\Providers;

use App\Auth\VatsimConnectProvider;
use App\Enums\Permission;
use App\Models\Fir;
use App\Models\User;
use App\Services\DisplayTime;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\ExceptionResponse;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Resolved fresh each time, so it always follows whoever is signed in.
        $this->app->bind(DisplayTime::class, fn () => DisplayTime::for(auth()->user()));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Administrators may do everything. Every permission is a gate of the same name, granted
        // through the user's roles; given a FIR, only roles held across the hub or in it count.
        Gate::before(fn (User $user) => $user->isAdministrator() ? true : null);

        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user, ?Fir $fir = null) => $user->hasPermission($permission, $fir));
        }

        // Generous for people clicking around, but stops scripts hammering bookings and admin pages.
        RateLimiter::for('signed-in', fn (Request $request) => Limit::perMinute(120)->by((string) $request->user()?->id));
        RateLimiter::for('sign-in', fn (Request $request) => Limit::perMinute(10)->by((string) $request->ip()));

        Socialite::extend('vatsim', fn () => Socialite::buildProvider(VatsimConnectProvider::class, config('services.vatsim'))
            ->setBaseUrl(config('services.vatsim.base_url')));

        Inertia::handleExceptionsUsing(function (ExceptionResponse $response) {
            $status = $response->statusCode();
            $request = $response->request;

            // Keep Laravel's detailed error page for server errors while debugging, and plain JSON for API clients.
            $debugging = $status >= 500 && config('app.debug');
            $wantsJson = $request->expectsJson() && ! $request->inertia();

            if (in_array($status, [403, 404, 419, 429, 500, 503], true) && ! $debugging && ! $wantsJson) {
                return $response->render('Error', ['status' => $status])->withSharedData();
            }

            return null;
        });
    }
}
