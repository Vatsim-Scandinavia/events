<?php

namespace App\Http\Controllers\Admin;

use App\Auth\Authorization;
use App\Auth\Controllers;
use App\Auth\Roles;
use App\Auth\Where;
use App\Enums\ControllerGrantOrigin;
use App\Http\Controllers\Controller;
use App\Models\ControllerGrant;
use App\Models\Fir;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lists everyone who has signed in to the hub, and shows the roles each person holds across
 * the hub or in a single FIR. Roles are given and taken away by RoleAssignmentController.
 */
class UserController extends Controller
{
    /**
     * Searching ignores case on every database. PostgreSQL's like doesn't, and won't compare the
     * numeric CID with text, so it uses ilike and a cast instead.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $showEmail = $request->user()->can('administer');
        $role = $request->integer('role') ?: null;
        $fir = $request->integer('fir') ?: null;

        $postgres = User::query()->getConnection()->getDriverName() === 'pgsql';
        $like = $postgres ? 'ilike' : 'like';
        $cid = $postgres ? 'vatsim_cid::text' : 'vatsim_cid';

        $users = User::query()
            ->with('roleAssignments.role', 'roleAssignments.fir')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereRaw("name {$like} ? escape '!'", ['%'.$this->literal($search).'%'])
                // Only those who can see email addresses can search by them.
                ->when($showEmail, fn ($query) => $query->orWhereRaw("email {$like} ? escape '!'", ['%'.$this->literal($search).'%']))
                ->orWhereRaw("{$cid} like ? escape '!'", [$this->literal($search).'%'])))
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

    public function edit(Request $request, User $user, Authorization $authorization, Controllers $controllers): Response
    {
        $roles = Role::query()->orderBy('name')->get();
        // Only the FIRs the signed-in user may give roles in.
        $firs = Fir::query()->orderBy('icao')->get(['id', 'icao', 'name'])
            ->filter(fn (Fir $fir) => $authorization->managesUsers($request->user(), Where::in($fir)))
            ->values();

        return Inertia::render('Admin/Users/Edit', [
            'user' => $this->summary($user, $request->user()->can('administer')),
            // Roles held across the hub first, then those held in one FIR.
            'assignments' => $user->roleAssignments()->with('role', 'fir')->get()
                ->sortBy(fn (RoleAssignment $assignment) => [$assignment->fir_id === null ? 0 : 1, $assignment->role->name, $assignment->fir?->icao])
                ->map(fn (RoleAssignment $assignment) => [
                    'id' => $assignment->id,
                    'role' => $assignment->role->name,
                    'fir' => $assignment->fir ? ['icao' => $assignment->fir->icao, 'name' => $assignment->fir->name] : null,
                    'canRemove' => $authorization->mayGrant($request->user(), $assignment->role, Where::at($assignment->fir)),
                ])
                ->values(),
            'roles' => $roles->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => Roles::permissionLabels($role),
                'grantableIn' => $authorization->grantableIn($request->user(), $role, $firs),
                'hubOnly' => Roles::isAdministrator($role),
            ]),
            'firs' => $firs,
            'controller' => $this->controller($request->user(), $user, $firs, $authorization, $controllers),
        ]);
    }

    /**
     * Where the user is a Controller, and where the signed-in user may make them one by hand.
     *
     * @param  Collection<int, Fir>  $firs
     * @return array<string, mixed>
     */
    private function controller(User $actor, User $user, Collection $firs, Authorization $authorization, Controllers $controllers): array
    {
        $source = $controllers->source();

        return [
            'administrator' => $authorization->isAdministrator($user),
            'byRule' => $controllers->ruleApplies($user),
            'ruleInCharge' => $controllers->ruleIsInCharge(),
            'source' => $source?->controllerSourceName(),
            // Hub-wide first, then by FIR.
            'grants' => $user->controllerGrants()->with('fir')->get()
                ->sortBy(fn (ControllerGrant $grant) => $grant->fir?->icao ?? '')
                ->map(fn (ControllerGrant $grant) => [
                    'id' => $grant->id,
                    'fir' => $grant->fir ? ['icao' => $grant->fir->icao, 'name' => $grant->fir->name] : null,
                    'fromSource' => $grant->origin === ControllerGrantOrigin::Source,
                    'canRemove' => $grant->origin === ControllerGrantOrigin::Hand && $authorization->managesUsers($actor, Where::at($grant->fir)),
                ])
                ->values(),
            'grantableIn' => $controllers->grantableIn($actor, $firs),
        ];
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

    /**
     * The search term for a LIKE pattern, matching % and _ as typed rather than as wildcards.
     */
    private function literal(string $search): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
    }
}
