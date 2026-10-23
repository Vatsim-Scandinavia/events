<?php

namespace App\Auth;

use App\Enums\AssignmentSource;
use App\Enums\ControllerRating;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\VaccIdentity;
use Illuminate\Database\Eloquent\Builder;

/**
 * Hands out the Controller role automatically, when an admin has switched that on: everyone
 * rated S1 or above in the vACC's subdivision holds it, and loses it when they leave the
 * subdivision or lose their rating.
 *
 * Only roles the hub gave itself are taken away. Controller roles admins give by hand (to
 * visiting controllers, say) are left alone.
 */
final class ControllerRole
{
    public function __construct(private VaccIdentity $identity) {}

    /**
     * Switched on, with a subdivision to check against.
     */
    public function isAutomatic(): bool
    {
        return $this->identity->controllerRoleIsAutomatic() && $this->subdivision() !== null;
    }

    public function qualifies(User $user): bool
    {
        return $this->isAutomatic()
            && $user->subdivision !== null
            && strtoupper($user->subdivision) === $this->subdivision()
            && ($user->rating?->value ?? 0) >= ControllerRating::S1->value;
    }

    /**
     * Give or take away the user's automatic Controller role to match their VATSIM details.
     */
    public function sync(User $user): void
    {
        if (! $this->isAutomatic()) {
            return;
        }

        $role = Role::findByName(Roles::CONTROLLER);
        $assignments = $user->roleAssignments()->where('role_id', $role->id)->whereNull('fir_id');

        if ($this->qualifies($user)) {
            if (! $assignments->exists()) {
                $user->assignRole($role, null, AssignmentSource::Automatic);
            }
        } else {
            $assignments->where('source', AssignmentSource::Automatic)->delete();
            $user->unsetRelation('roles');
        }
    }

    /**
     * Re-check everyone it could affect, after the setting or the subdivision changed. Switching it off hands
     * the roles it gave over to admins, so nobody loses access without someone deciding so.
     */
    public function syncEveryone(): void
    {
        if (! $this->isAutomatic()) {
            RoleAssignment::query()->where('source', AssignmentSource::Automatic)->update(['source' => AssignmentSource::Manual]);

            return;
        }

        $this->candidates()->chunkById(200, fn ($users) => $users->each(fn (User $user) => $this->sync($user)));
    }

    /**
     * Users whose VATSIM details could change their automatic role: everyone it was given to,
     * and everyone last seen in the subdivision (who may have been rated since).
     *
     * @return Builder<User>
     */
    public function candidates(): Builder
    {
        $role = Role::findByName(Roles::CONTROLLER);

        return User::query()->where(fn (Builder $query) => $query
            ->whereRaw('upper(subdivision) = ?', [$this->subdivision()])
            ->orWhereHas('roleAssignments', fn (Builder $query) => $query
                ->where('role_id', $role->id)
                ->whereNull('fir_id')
                ->where('source', AssignmentSource::Automatic)));
    }

    private function subdivision(): ?string
    {
        $subdivision = $this->identity->effective()['subdivision'];

        return $subdivision !== null ? strtoupper($subdivision) : null;
    }
}
