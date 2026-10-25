<?php

namespace App\Auth;

use App\Enums\Permission;
use App\Models\Fir;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The roles the hub ships with, and the rules for who may hand out which role.
 *
 * Built-in roles can't be renamed or deleted. Administrator always holds every permission
 * and applies across the hub. The others start out with the permissions below, which admins
 * can change later. Someone holding no role at all is a Pilot.
 */
final class Roles
{
    public const string ADMINISTRATOR = 'Administrator';

    public const string EVENT_STAFF = 'Event Staff';

    public const string VACC_STAFF = 'vACC Staff';

    public const string CONTROLLER = 'Controller';

    /** Not a role: what someone without any role is shown as. */
    public const string PILOT = 'Pilot';

    /**
     * The permissions each built-in role starts out with. Administrator holds everything.
     *
     * @var array<string, list<Permission>>
     */
    private const array DEFAULTS = [
        self::EVENT_STAFF => [Permission::ManageEvents, Permission::ManageStaffing],
        self::VACC_STAFF => [Permission::ViewEvents],
        self::CONTROLLER => [Permission::BookPositions],
    ];

    /**
     * Makes sure every built-in role exists, and that Administrator holds every permission.
     * Other built-in roles get their permissions only when first created.
     */
    public static function sync(): void
    {
        Role::query()->updateOrCreate(['name' => self::ADMINISTRATOR], ['permissions' => Permission::cases()]);

        foreach (self::DEFAULTS as $name => $permissions) {
            Role::query()->firstOrCreate(['name' => $name], ['permissions' => $permissions]);
        }
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_map(fn (Permission $permission) => $permission->value, Permission::cases());
    }

    public static function isAdministrator(Role $role): bool
    {
        return $role->name === self::ADMINISTRATOR;
    }

    public static function isBuiltIn(Role $role): bool
    {
        return self::isAdministrator($role) || array_key_exists($role->name, self::DEFAULTS);
    }

    /**
     * Whether the permissions allow no more than the user can do across the hub, or in the
     * given FIR (counting their hub-wide roles too).
     *
     * @param  iterable<string>  $permissions
     */
    public static function within(User $user, iterable $permissions, ?Fir $fir = null): bool
    {
        return collect($permissions)->diff($user->permissionNames($fir))->isEmpty();
    }

    /**
     * Whether the user may give or take away the role in the given FIR, or across the hub when
     * none is given: they must manage users there, and the role must allow no more than they
     * can do there, so managing users never becomes a way to gain permissions.
     *
     * Administrator also runs the hub's configuration, which no permission covers, so only
     * Administrators can hand it out or take it away.
     */
    public static function canGrant(User $user, Role $role, ?Fir $fir = null): bool
    {
        if (self::isAdministrator($role)) {
            return $user->isAdministrator();
        }

        return $user->managesUsersIn($fir) && self::within($user, $role->permissions->pluck('value'), $fir);
    }

    /**
     * Where the user may give the role: across the hub (null) and the FIRs (by id), in that
     * order. Empty when they can't give it anywhere.
     *
     * @param  Collection<int, Fir>  $firs
     * @return list<int|null>
     */
    public static function grantableIn(User $user, Role $role, Collection $firs): array
    {
        $places = self::isAdministrator($role) ? [null] : [null, ...$firs->all()];

        return collect($places)
            ->filter(fn (?Fir $fir) => self::canGrant($user, $role, $fir))
            ->map(fn (?Fir $fir) => $fir?->id)
            ->values()
            ->all();
    }

    /**
     * Labels for the permissions a role grants.
     *
     * @return list<string>
     */
    public static function permissionLabels(Role $role): array
    {
        return $role->permissions->map(fn (Permission $permission) => $permission->label())->values()->all();
    }

    /**
     * What the user holds, e.g. "Administrator" or "Event Staff · EKDK" for a role held in one
     * FIR, or just "Pilot" when they hold no role.
     *
     * @return Collection<int, string>
     */
    public static function labelsFor(User $user): Collection
    {
        $labels = $user->loadMissing('roleAssignments.role', 'roleAssignments.fir')->roleAssignments
            ->map(fn (RoleAssignment $assignment) => self::label($assignment->role, $assignment->fir))
            ->sort()
            ->values();

        return $labels->isEmpty() ? collect([self::PILOT]) : $labels;
    }

    /**
     * A role as held across the hub ("Event Staff") or in one FIR ("Event Staff · EKDK").
     */
    public static function label(Role $role, ?Fir $fir): string
    {
        return $fir ? "{$role->name} · {$fir->icao}" : $role->name;
    }
}
