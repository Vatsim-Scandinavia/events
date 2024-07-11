<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EventPolicy
{
    use HandlesAuthorization;

    public function index(User $user)
    {
        return $user->isModeratorOrAbove();
    }

    public function view(User $user, Event $event)
    {
        return $user->isModeratorOrAbove() || $user->isModerator($event->area) || $user->is($event->user);
    }

    public function create(User $user) 
    {
        return $user->isModeratorOrAbove();
    }
}
