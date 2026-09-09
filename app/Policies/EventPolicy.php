<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\EventCollaboration;
use App\Models\User;
use App\PermissionName;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->teamsWithPermission(PermissionName::ViewEvents)->exists();
    }

    public function create(User $user): bool
    {
        return $user->teamsWithPermission(PermissionName::ManageEvents)->exists();
    }

    public function view(User $user, Event $event): bool
    {
        return $this->hasAccess($user, $event, PermissionName::ViewEvents);
    }

    public function update(User $user, Event $event): bool
    {
        return $this->hasAccess($user, $event, PermissionName::ManageEvents);
    }

    public function publish(User $user, Event $event): bool
    {
        return $this->manageOwner($user, $event);
    }

    public function manageOwner(User $user, Event $event): bool
    {
        return $user->can(PermissionName::ManageEvents, $event->owner);
    }

    private function hasAccess(User $user, Event $event, PermissionName $permission): bool
    {
        return $user->can($permission, $event->owner) || $event->collaborations()->whereNotNull('accepted_at')
            ->with('team')->get()->contains(fn (EventCollaboration $collaboration): bool => $user->can($permission, $collaboration->team));
    }
}
