<?php

namespace App\Http\Middleware;

use App\Auth\DevLogin;
use App\Auth\Roles;
use App\Enums\Permission;
use App\Enums\SignInProvider;
use App\Services\VaccIdentity;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'vacc' => fn () => app(VaccIdentity::class)->effective(),
            'auth' => [
                'user' => fn () => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'cid' => $request->user()->vatsim_cid,
                    'rating' => $request->user()->rating?->label(),
                    'roles' => Roles::labelsFor($request->user()),
                    'isAdministrator' => $request->user()->isAdministrator(),
                    // Which permissions the user holds, keyed by permission name.
                    'can' => collect(Permission::cases())
                        ->mapWithKeys(fn (Permission $permission) => [$permission->value => $request->user()->can($permission->value)]),
                ] : null,
                'devLogin' => DevLogin::enabled(),
                'signIn' => [
                    'url' => route('auth.redirect', SignInProvider::configured(), absolute: false),
                    'label' => 'Sign in with '.SignInProvider::configured()->label(),
                ],
            ],
            // The IANA time zone event times are shown in; UTC (zulu) unless the user picked another.
            'displayTimezone' => fn () => $request->user()?->displayTimezone() ?? 'UTC',
        ];
    }
}
