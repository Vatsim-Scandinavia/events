<?php

namespace App\Auth;

use App\Models\Fir;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Giving and taking away roles, Hub-wide or in a single FIR. Every write to role assignments
 * goes through here, so its guards hold however a role is given: Administrator only ever applies
 * Hub-wide, and the Hub always keeps at least one Administrator.
 *
 * give() is the plain write, for the Hub itself (seeding, commands). giveAs() and removeAs() act on an admin's behalf, and first check with Authorization
 * that they may.
 */
final class RoleAssignments
{
    public function __construct(private Authorization $authorization) {}

    /**
     * Give the user a role Hub-wide, or only in the given FIR. Holding it already changes nothing.
     *
     * @throws InvalidArgumentException when Administrator is given in a single FIR
     */
    public function give(User $user, Role|string $role, ?Fir $fir = null): RoleAssignment
    {
        $role = $role instanceof Role ? $role : Role::findByName($role);

        if ($fir !== null && Roles::isAdministrator($role)) {
            throw new InvalidArgumentException('Administrator always applies across the hub.');
        }

        $assignment = $user->roleAssignments()->firstOrCreate(['role_id' => $role->id, 'fir_id' => $fir?->id]);
        $user->unsetRelation('roles');

        return $assignment;
    }

    /**
     * Give a role on the actor's behalf.
     *
     * @throws ValidationException when the actor may not give it there, or the user holds it already
     */
    public function giveAs(User $actor, User $user, Role $role, ?Fir $fir): RoleAssignment
    {
        $this->ensureGrantable($actor, $role, $fir);

        if ($fir !== null && Roles::isAdministrator($role)) {
            throw ValidationException::withMessages(['roles' => 'Administrator always applies across the hub.']);
        }

        $assignment = $this->give($user, $role, $fir);

        // Held already, including when someone else gave it a moment ago: the database keeps one copy.
        if (! $assignment->wasRecentlyCreated) {
            throw ValidationException::withMessages(['roles' => "{$user->name} already has ".Roles::label($role, $fir).'.']);
        }

        return $assignment;
    }

    /**
     * Take a role away on the actor's behalf.
     *
     * @throws ValidationException when the actor may not, or it would leave the Hub without an
     *                             Administrator
     */
    public function removeAs(User $actor, RoleAssignment $assignment): void
    {
        $assignment->loadMissing('role', 'fir', 'user');
        $this->ensureGrantable($actor, $assignment->role, $assignment->fir);

        DB::transaction(function () use ($assignment) {
            // Two admins removing each other at once must not both pass the check below.
            $this->administratorAssignments()->lockForUpdate()->get();

            $assignment->delete();

            // Rolled back with the transaction, so the Hub is never left without an Administrator.
            if (Roles::isAdministrator($assignment->role) && ! $this->administratorAssignments()->exists()) {
                throw ValidationException::withMessages([
                    'roles' => 'The hub needs at least one Administrator, so this person has to stay one.',
                ]);
            }
        });

        $assignment->user->unsetRelation('roles');
    }

    /**
     * @throws ValidationException
     */
    private function ensureGrantable(User $actor, Role $role, ?Fir $fir): void
    {
        if (! $this->authorization->managesUsers($actor, Where::at($fir))) {
            throw ValidationException::withMessages([
                'roles' => $fir
                    ? 'You can only give or take away roles in FIRs where you manage users.'
                    : 'You can only give or take away roles across the hub if you manage users across the hub.',
            ]);
        }

        if (! $this->authorization->mayGrant($actor, $role, Where::at($fir))) {
            throw ValidationException::withMessages([
                'roles' => "You can’t give or take away {$role->name}, because it allows more than you can do yourself.",
            ]);
        }
    }

    /**
     * @return Builder<RoleAssignment>
     */
    private function administratorAssignments(): Builder
    {
        return RoleAssignment::query()->whereNull('fir_id')->whereRelation('role', 'name', Roles::ADMINISTRATOR);
    }
}
