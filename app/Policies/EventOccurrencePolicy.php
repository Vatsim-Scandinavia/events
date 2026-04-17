<?php

namespace App\Policies;

use App\Models\EventOccurrence;
use App\Models\User;

class EventOccurrencePolicy
{
    /**
     * Determine whether the user can cancel the event occurrence.
     */
    public function cancel(User $user, EventOccurrence $eventOccurrence): bool
    {
        if (!$user->hasPermissionTo('manage events')) {
            return false;
        }

        return $eventOccurrence->event->created_by === $user->id
            || $user->hasPermissionTo('manage events created by others');
    }

    /**
     * Determine whether the user can restore the event occurrence.
     */
    public function restore(User $user, EventOccurrence $eventOccurrence): bool
    {
        if (!$user->hasPermissionTo('manage events')) {
            return false;
        }

        return $eventOccurrence->event->created_by === $user->id
            || $user->hasPermissionTo('manage events created by others');
    }
}
