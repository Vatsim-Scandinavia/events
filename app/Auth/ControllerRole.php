<?php

namespace App\Auth;

use App\Enums\AssignmentSource;
use App\Enums\ControllerRating;
use App\Integrations\Integrations;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\VaccIdentity;
use Illuminate\Database\Eloquent\Builder;

/**
 * Hands out the Controller role automatically. By default that's the hub's own rule, when an
 * admin has switched it on: everyone rated S1 or above in the vACC's subdivision holds it, and
 * loses it when they leave the subdivision or lose their rating. An Integration feature can take
 * over as the Controller source (see ControllerSource), and the hub's rule then steps aside.
 *
 * Only roles given automatically are taken away. Controller roles admins give by hand (to
 * visiting controllers, say) are left alone.
 */
final class ControllerRole
{
    public function __construct(private VaccIdentity $identity, private Integrations $integrations) {}

    /**
     * The Integration feature in charge of the Controller role, or null when the hub's rule is.
     */
    public function source(): ?ControllerSource
    {
        return $this->integrations->controllerSource();
    }

    /**
     * The hub's own rule is in charge and switched on, with a subdivision to check against.
     */
    public function isAutomatic(): bool
    {
        return $this->source() === null && $this->identity->controllerRoleIsAutomatic() && $this->subdivision() !== null;
    }

    private function qualifies(User $user): bool
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
        if ($source = $this->source()) {
            $source->syncController($user);

            return;
        }

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
     * Re-check everyone the hub's rule could affect, after the setting or the subdivision changed.
     * Switching it off hands the roles it gave over to admins, so nobody loses access without
     * someone deciding so. While a Controller source is in charge, the roles are its to manage.
     */
    public function syncEveryone(): void
    {
        if ($this->source() !== null) {
            return;
        }

        if (! $this->isAutomatic()) {
            $this->handOver();

            return;
        }

        $this->candidates()->chunkById(200, fn ($users) => $users->each(fn (User $user) => $this->sync($user)));
    }

    /**
     * Turn every automatic Controller role into one given by hand, when whatever gave them stops
     * being in charge, so nobody loses access without someone deciding so.
     */
    public function handOver(): void
    {
        RoleAssignment::query()->where('source', AssignmentSource::Automatic)->update(['source' => AssignmentSource::Manual]);
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
