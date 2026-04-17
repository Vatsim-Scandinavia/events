<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Event $event): bool
    {
        if ($event->status === 'published') {
            // Published events can be viewed by anyone, even guests
            return true;
        }
        // Only authenticated users with the right permission can view drafts
        return $user?->hasPermissionTo('manage events') ?? false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage events');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Event $event): bool
    {
        if (!$user->hasPermissionTo('manage events')) {
            return false;
        }

        return $event->created_by === $user->id
            || $user->hasPermissionTo('manage events created by others');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Event $event): bool
    {
        if (!$user->hasPermissionTo('manage events')) {
            return false;
        }

        return $event->created_by === $user->id
            || $user->hasPermissionTo('manage events created by others');
    }
}
