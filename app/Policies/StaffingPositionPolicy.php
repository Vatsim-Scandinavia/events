<?php

namespace App\Policies;

use App\Models\StaffingPosition;
use App\Models\User;

class StaffingPositionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('manage events');
    }

    public function view(User $user, StaffingPosition $staffingPosition): bool
    {
        return $user->hasPermissionTo('manage events');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage events');
    }

    public function update(User $user, StaffingPosition $staffingPosition): bool
    {
        if (! $user->hasPermissionTo('manage events')) {
            return false;
        }

        return $staffingPosition->section->staffing->event->created_by === $user->id
            || $user->hasPermissionTo('manage events created by others');
    }

    public function delete(User $user, StaffingPosition $staffingPosition): bool
    {
        if (! $user->hasPermissionTo('manage events')) {
            return false;
        }

        return $staffingPosition->section->staffing->event->created_by === $user->id
            || $user->hasPermissionTo('manage events created by others');
    }

    public function restore(User $user, StaffingPosition $staffingPosition): bool
    {
        return $this->delete($user, $staffingPosition);
    }

    public function forceDelete(User $user, StaffingPosition $staffingPosition): bool
    {
        return $this->delete($user, $staffingPosition);
    }
}
