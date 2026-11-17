<?php

namespace App\Auth;

use App\Enums\Permission;
use App\Models\Fir;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The roles the hub ships with. Who may hand out which role is decided by Authorization.
 *
 * Built-in roles can't be renamed or deleted. Administrator holds every permission by rule,
 * whatever its stored permissions say, and applies across the hub. Someone holding no role at
 * all is a Pilot.
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
     * The roles every Hub has, created by the roles migration. Administrator holds everything by
     * rule and stores no permissions; the others start out with a few, which admins can change.
     *
     * @var list<string>
     */
    private const array BUILT_IN = [self::ADMINISTRATOR, self::EVENT_STAFF, self::VACC_STAFF, self::CONTROLLER];

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
        return in_array($role->name, self::BUILT_IN, true);
    }

    /**
     * The permissions a role grants: every one for Administrator, otherwise those it stores.
     *
     * @return Collection<int, Permission>
     */
    public static function permissionsOf(Role $role): Collection
    {
        return self::isAdministrator($role) ? collect(Permission::cases()) : $role->permissions->values();
    }

    /**
     * Labels for the permissions a role grants.
     *
     * @return list<string>
     */
    public static function permissionLabels(Role $role): array
    {
        return self::permissionsOf($role)->map(fn (Permission $permission) => $permission->label())->values()->all();
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
