<?php

namespace App\Providers;

use App\Auth\HandoverProvider;
use App\Auth\VatsimConnectProvider;
use App\Enums\Permission;
use App\Integrations\Integrations;
use App\Models\Fir;
use App\Models\User;
use App\Services\DisplayTime;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\ViteException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\ExceptionResponse;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

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

        // Roles and the hub's settings, FIRs and event types are shared by every FIR, so only
        // Administrators may change them.
        Gate::define('administer', fn (User $user) => $user->isAdministrator());

        // Generous for people clicking around, but stops scripts hammering bookings and admin pages.
        RateLimiter::for('signed-in', fn (Request $request) => Limit::perMinute(120)->by((string) $request->user()?->id));
        RateLimiter::for('sign-in', fn (Request $request) => Limit::perMinute(10)->by((string) $request->ip()));
        // The public API has no sign-in, so it's limited per address.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by((string) $request->ip()));

        app(Integrations::class)->boot();

        Socialite::extend('vatsim', fn () => Socialite::buildProvider(VatsimConnectProvider::class, config('services.vatsim'))
            ->setBaseUrl(config('services.vatsim.base_url')));
        Socialite::extend('handover', fn () => Socialite::buildProvider(HandoverProvider::class, config('services.handover'))
            ->setBaseUrl(config('services.handover.base_url')));

        Inertia::handleExceptionsUsing(function (ExceptionResponse $response) {
            $status = $response->statusCode();
            $request = $response->request;

            // The branded page needs the frontend build too, so when that's what failed, fall back
            // to the plain page in resources/views/errors.
            if ($this->isFrontendFailure($response->exception)) {
                return null;
            }

            // Keep Laravel's detailed error page for server errors while debugging, and plain JSON for API clients.
            $debugging = $status >= 500 && config('app.debug');
            $wantsJson = $request->expectsJson() && ! $request->inertia();

            if (in_array($status, [403, 404, 419, 429, 500, 503], true) && ! $debugging && ! $wantsJson) {
                return $response->render('Error', ['status' => $status])->withSharedData();
            }

            return null;
        });
    }

    /**
     * Whether the exception, or one it wraps, is the frontend build missing or broken.
     */
    private function isFrontendFailure(Throwable $exception): bool
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof ViteException) {
                return true;
            }
        }

        return false;
    }
}
