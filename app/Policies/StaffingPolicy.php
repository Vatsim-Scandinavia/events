<?php

namespace App\Policies;

use App\Models\Staffing;
use App\Models\User;

class StaffingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('manage events');
    }

    public function view(User $user, Staffing $staffing): bool
    {
        return $user->hasPermissionTo('manage events');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage events');
    }

    public function update(User $user, Staffing $staffing): bool
    {
        if (! $user->hasPermissionTo('manage events')) {
            return false;
        }

        return $staffing->event->created_by === $user->id
            || $user->hasPermissionTo('manage events created by others');
    }

    public function delete(User $user, Staffing $staffing): bool
    {
        if (! $user->hasPermissionTo('manage events')) {
            return false;
        }

        return $staffing->event->created_by === $user->id
            || $user->hasPermissionTo('manage events created by others');
    }

    public function restore(User $user, Staffing $staffing): bool
    {
        return $this->delete($user, $staffing);
    }

    public function forceDelete(User $user, Staffing $staffing): bool
    {
        return $this->delete($user, $staffing);
    }
}
