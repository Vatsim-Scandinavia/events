<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Event;
use App\Models\User;

/**
 * What someone may do with an event, which depends on the FIR that runs it: a role held in
 * EKDK covers EKDK's events only, a role held across the hub covers every event.
 *
 * Administrators pass every check before these run (see AppServiceProvider).
 */
class EventPolicy
{
    /**
     * Everyone sees published events. Drafts only show to staff who can view or manage events
     * in the event's FIR.
     */
    public function view(?User $user, Event $event): bool
    {
        if (! $event->isDraft()) {
            return true;
        }

        return $user !== null
            && ($user->hasPermissionFor(Permission::ManageEvents, $event) || $user->hasPermissionFor(Permission::ViewEvents, $event));
    }

    /**
     * Make a Draft visible to everyone.
     */
    public function publish(User $user, Event $event): bool
    {
        return $user->hasPermissionFor(Permission::ManageEvents, $event);
    }

    public function update(User $user, Event $event): bool
    {
        return $user->hasPermissionFor(Permission::ManageEvents, $event);
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->hasPermissionFor(Permission::ManageEvents, $event);
    }

    /**
     * Cancel or restore the event, or one of its dates.
     */
    public function cancel(User $user, Event $event): bool
    {
        return $user->hasPermissionFor(Permission::ManageEvents, $event);
    }

    /**
     * Set up positions and shifts, lay and publish rosters, and remove anyone's booking.
     */
    public function manageStaffing(User $user, Event $event): bool
    {
        return $user->hasPermissionFor(Permission::ManageStaffing, $event);
    }

    /**
     * See staffing as staff do (everyone's interest, rosters before they're published),
     * without necessarily being able to change it.
     */
    public function viewStaffing(User $user, Event $event): bool
    {
        return $this->manageStaffing($user, $event) || $user->hasPermissionFor(Permission::ViewEvents, $event);
    }
}
