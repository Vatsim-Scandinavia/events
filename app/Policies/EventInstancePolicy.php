<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\EventInstance;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EventInstancePolicy
{
    use HandlesAuthorization;

    public function index(User $user)
    {
        return $user->isModeratorOrAbove();
    }

    public function view(?User $user, EventInstance $eventInstance)
    {
        return $user && ($user->isModeratorOrAbove() || $user->isModerator() || $user->is($eventInstance->event->user)) || $eventInstance->event->calendar->public;
    }

    public function create(User $user)
    {
        return $user->isModeratorOrAbove();
    }

    public function update(User $user, EventInstance $eventInstance)
    {
        return $user->isModerator() || $user->isAdmin();
    }

    public function destroy(User $user, EventInstance $eventInstance)
    {
        return $user->isModerator() || $user->isAdmin();
    }

    public function restore(User $user, EventInstance $eventInstance)
    {
        return $user->isModerator() || $user->isAdmin();
    }
}
