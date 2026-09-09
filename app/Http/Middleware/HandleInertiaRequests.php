<?php

namespace App\Http\Middleware;

use App\Models\Event;
use App\Models\EventRoster;
use App\PermissionName;
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
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

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
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'can_manage_users' => $request->user()?->can(PermissionName::ManageRoles) ?? false,
                'can_manage_firs' => $request->user()?->can(PermissionName::ManageFirs) ?? false,
                'can_view_audit_logs' => $request->user()?->can(PermissionName::ViewAuditLogs) ?? false,
                'can_view_events' => $request->user()?->can('viewAny', Event::class) ?? false,
                'can_view_rosters' => $request->user()?->can('viewAny', EventRoster::class) ?? false,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
