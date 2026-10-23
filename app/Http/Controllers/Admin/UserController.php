<?php

namespace App\Http\Controllers\Admin;

use App\Auth\Roles;
use App\Enums\AssignmentSource;
use App\Http\Controllers\Controller;
use App\Models\Fir;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lists everyone who has signed in to the hub, and shows the roles each person holds across
 * the hub or in a single FIR. Roles are given and taken away by RoleAssignmentController.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $showEmail = $request->user()->isAdministrator();
        $role = $request->integer('role') ?: null;
        $fir = $request->integer('fir') ?: null;

        $users = User::query()
            ->with('roleAssignments.role', 'roleAssignments.fir')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                // Only those who can see email addresses can search by them.
                ->when($showEmail, fn ($query) => $query->orWhere('email', 'like', "%{$search}%"))
                ->orWhere('vatsim_cid', 'like', "{$search}%")))
            // Both filters have to match the same assignment: Event Staff *in* EKDK.
            ->when($role || $fir, fn ($query) => $query->whereHas('roleAssignments', fn (Builder $query) => $query
                ->when($role, fn ($query) => $query->where('role_id', $role))
                ->when($fir, fn ($query) => $query->where('fir_id', $fir))))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $user) => $this->summary($user, $showEmail));

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => ['search' => $search, 'role' => $role, 'fir' => $fir],
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
            'firs' => Fir::query()->orderBy('icao')->get(['id', 'icao', 'name']),
        ]);
    }

    public function edit(Request $request, User $user): Response
    {
        $roles = Role::query()->orderBy('name')->get();
        // Only the FIRs the signed-in user may give roles in.
        $firs = Fir::query()->orderBy('icao')->get(['id', 'icao', 'name'])
            ->filter(fn (Fir $fir) => $request->user()->managesUsersIn($fir))
            ->values();

        return Inertia::render('Admin/Users/Edit', [
            'user' => $this->summary($user, $request->user()->isAdministrator()),
            // Roles held across the hub first, then those held in one FIR.
            'assignments' => $user->roleAssignments()->with('role', 'fir')->get()
                ->sortBy(fn (RoleAssignment $assignment) => [$assignment->fir_id === null ? 0 : 1, $assignment->role->name, $assignment->fir?->icao])
                ->map(fn (RoleAssignment $assignment) => [
                    'id' => $assignment->id,
                    'role' => $assignment->role->name,
                    'fir' => $assignment->fir ? ['icao' => $assignment->fir->icao, 'name' => $assignment->fir->name] : null,
                    // Given by the hub's automatic Controller role, which would give it straight back.
                    'automatic' => $assignment->source === AssignmentSource::Automatic,
                    'canRemove' => $assignment->source !== AssignmentSource::Automatic && Roles::canGrant($request->user(), $assignment->role, $assignment->fir),
                ])
                ->values(),
            'roles' => $roles->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => Roles::permissionLabels($role),
                'grantableIn' => Roles::grantableIn($request->user(), $role, $firs),
                'hubOnly' => Roles::isAdministrator($role),
            ]),
            'firs' => $firs,
        ]);
    }

    /**
     * Email addresses are only sent to Administrators; others see name, CID and roles.
     *
     * @return array<string, mixed>
     */
    private function summary(User $user, bool $showEmail): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            ...($showEmail ? ['email' => $user->email] : []),
            'cid' => $user->vatsim_cid,
            'rating' => $user->rating?->label(),
            'roles' => Roles::labelsFor($user),
            'joinedAt' => $user->created_at?->toDateString(),
        ];
    }
}
