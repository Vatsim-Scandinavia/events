<?php

namespace App\Policies;

use App\Models\StaffingSection;
use App\Models\User;

class StaffingSectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('manage events');
    }

    public function view(User $user, StaffingSection $staffingSection): bool
    {
        return $user->hasPermissionTo('manage events');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage events');
    }

    public function update(User $user, StaffingSection $staffingSection): bool
    {
        if (! $user->hasPermissionTo('manage events')) {
            return false;
        }

        return $staffingSection->staffing->event->created_by === $user->id
            || $user->hasPermissionTo('manage events created by others');
    }

    public function delete(User $user, StaffingSection $staffingSection): bool
    {
        if (! $user->hasPermissionTo('manage events')) {
            return false;
        }

        return $staffingSection->staffing->event->created_by === $user->id
            || $user->hasPermissionTo('manage events created by others');
    }

    public function restore(User $user, StaffingSection $staffingSection): bool
    {
        return $this->delete($user, $staffingSection);
    }

    public function forceDelete(User $user, StaffingSection $staffingSection): bool
    {
        return $this->delete($user, $staffingSection);
    }
}
