<?php

namespace App\Auth;

use App\Enums\Permission;
use App\Models\Event;
use App\Models\Fir;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Every rule for what an Account may do. It only checks, and never changes anything: giving and
 * taking away roles happens elsewhere.
 *
 * Callers normally ask through Laravel's gates and policies (`can`, `authorize`, `@can`), which
 * are defined on top of this. Administrators may do everything, everywhere.
 */
final class Authorization
{
    public function isAdministrator(User $user): bool
    {
        return $user->roles->contains(fn (Role $role) => Roles::isAdministrator($role) && $role->pivot->fir_id === null);
    }

    /**
     * Whether one of the user's roles grants the permission there.
     */
    public function holds(User $user, Permission $permission, Where $where): bool
    {
        if ($this->isAdministrator($user)) {
            return true;
        }

        return $this->rolesAt($user, $where)->contains(fn (Role $role) => $role->grants($permission));
    }

    /**
     * The permissions the user holds there, by name.
     *
     * @return Collection<int, string>
     */
    public function permissionsAt(User $user, Where $where): Collection
    {
        if ($this->isAdministrator($user)) {
            return collect(Roles::all());
        }

        return $this->rolesAt($user, $where)
            ->flatMap(fn (Role $role) => $role->permissions->pluck('value'))
            ->unique()
            ->values();
    }

    /**
     * The FIRs (by id) where the user holds any of the permissions, or null when they hold one
     * Hub-wide, which covers every FIR.
     *
     * @return list<int>|null
     */
    public function firsWhere(User $user, Permission ...$permissions): ?array
    {
        foreach ($permissions as $permission) {
            if ($this->holds($user, $permission, Where::hub())) {
                return null;
            }
        }

        return $user->roles
            ->filter(fn (Role $role) => $role->pivot->fir_id !== null
                && collect($permissions)->contains(fn (Permission $permission) => $role->grants($permission)))
            ->map(fn (Role $role) => (int) $role->pivot->fir_id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Whether the user may give and take away roles there. Managing users Hub-wide covers every FIR.
     */
    public function managesUsers(User $user, Where $where): bool
    {
        return $this->holds($user, Permission::ManageUsers, $where);
    }

    /**
     * Whether the permissions allow no more than the user can do there.
     *
     * @param  iterable<string>  $permissions
     */
    public function within(User $user, iterable $permissions, Where $where): bool
    {
        return collect($permissions)->diff($this->permissionsAt($user, $where))->isEmpty();
    }

    /**
     * Whether the user may give or take away the role there: they must manage users there, and
     * the role must allow no more than they can do there, so managing users never becomes a way
     * to gain permissions.
     *
     * Administrator also runs the Hub's configuration, which no permission covers, so only
     * Administrators can hand it out or take it away.
     */
    public function mayGrant(User $user, Role $role, Where $where): bool
    {
        if (Roles::isAdministrator($role)) {
            return $this->isAdministrator($user);
        }

        return $this->managesUsers($user, $where) && $this->within($user, $role->permissions->pluck('value'), $where);
    }

    /**
     * Where the user may give the role: Hub-wide (null) and the FIRs (by id), in that order.
     * Empty when they can't give it anywhere.
     *
     * @param  Collection<int, Fir>  $firs
     * @return list<int|null>
     */
    public function grantableIn(User $user, Role $role, Collection $firs): array
    {
        $places = Roles::isAdministrator($role) ? [null] : [null, ...$firs->all()];

        return collect($places)
            ->filter(fn (?Fir $fir) => $this->mayGrant($user, $role, Where::at($fir)))
            ->map(fn (?Fir $fir) => $fir?->id)
            ->values()
            ->all();
    }

    /**
     * Whether the user may staff the event (book shifts, show interest, be rostered): with a role
     * that lets them Hub-wide, or in the FIR that runs it.
     */
    public function isController(User $user, Event $event): bool
    {
        return $this->holds($user, Permission::BookPositions, Where::in($event->fir));
    }

    /**
     * Everyone who may staff the event, by the same rule as isController.
     *
     * @return Builder<User>
     */
    public function controllersFor(Event $event): Builder
    {
        return User::query()->whereHas('roles', fn (Builder $roles) => $roles
            ->where(fn (Builder $roles) => $roles
                ->where('name', Roles::ADMINISTRATOR)
                ->orWhereJsonContains('permissions', Permission::BookPositions->value))
            ->where(fn (Builder $roles) => $roles
                ->whereNull('role_assignments.fir_id')
                ->orWhere('role_assignments.fir_id', $event->fir_id)));
    }

    /**
     * The user's roles that count there.
     *
     * @return Collection<int, Role>
     */
    private function rolesAt(User $user, Where $where): Collection
    {
        return $user->roles->filter(fn (Role $role) => $where->covers($role->pivot->fir_id));
    }
}
