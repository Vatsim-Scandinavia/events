<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserIndexRequest;
use App\Models\RoleGrant;
use App\Models\Team;
use App\Models\User;
use App\RoleName;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(UserIndexRequest $request): Response
    {
        $search = trim($request->validated('search') ?? '');
        $role = $request->validated('role');
        $teamId = $request->filled('team_id') ? $request->integer('team_id') : null;
        $teams = Team::orderBy('code')->get(['id', 'code', 'name']);
        $roles = Role::where('guard_name', 'web')->whereNull('team_id')->get(['id', 'name'])->keyBy('id');

        $users = User::query()
            ->select(['cid', 'name_full', 'email', 'controller_rating', 'division', 'subdivision', 'created_at'])
            ->with(['assignedRoles', 'roleGrants'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereLike('name_full', '%'.$search.'%')
                        ->orWhereLike('email', '%'.$search.'%');

                    if (ctype_digit($search)) {
                        $query->orWhere('cid', $search);
                    }
                });
            })
            ->when($role === RoleName::Pilot->value, fn (Builder $query): Builder => $query
                ->whereDoesntHave('assignedRoles', fn (Builder $roles): Builder => $roles->where('guard_name', 'web')))
            ->when(($role !== null && $role !== RoleName::Pilot->value) || $teamId !== null,
                fn (Builder $query): Builder => $query->whereHas('assignedRoles', fn (Builder $roles): Builder => $roles
                    ->where('guard_name', 'web')
                    ->when($role !== null && $role !== RoleName::Pilot->value, fn (Builder $roles): Builder => $roles->where('name', $role))
                    ->when($teamId !== null, fn (Builder $roles): Builder => $roles->where('model_has_roles.team_id', $teamId))))
            ->orderBy('name_full')->orderBy('cid')
            ->paginate(15)->withQueryString()
            ->through(fn (User $user): array => [
                ...$user->only(['cid', 'name_full', 'email', 'controller_rating', 'division', 'subdivision']),
                'joined_at' => $user->created_at?->toDateString(),
                'roles' => $user->effectiveRoleNames()->all(),
                'grants' => $user->roleGrants->sortBy('id')->values()->map(fn (RoleGrant $grant): array => [
                    'id' => $grant->id,
                    'role' => $roles->get($grant->role_id)?->name,
                    'team_id' => $grant->team_id,
                    'source' => $grant->source,
                ])->all(),
            ]);

        return Inertia::render('users/index', [
            'users' => $users,
            'filters' => ['search' => $search, 'role' => $role, 'team_id' => $teamId],
            'teams' => $teams,
            'roles' => array_map(fn (RoleName $role): array => [
                'name' => $role->value,
                'is_global' => $role->isGlobal(),
                'assignable' => $role !== RoleName::Pilot,
            ], RoleName::cases()),
        ]);
    }
}
