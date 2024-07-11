<?php

namespace App\Policies;

use App\Models\Calendar;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CalendarPolicy
{
    use HandlesAuthorization;

    public function index(User $user) 
    {
        return $user->isAdmin();
    }

    public function view(User $user, Calendar $calendar) 
    {
        return $user->isModeratorOrAbove() || $calendar->public;
    }

    public function create(User $user) 
    {
        return $user->isAdmin();    
    }

    public function update(User $user, Calendar $calendar) 
    {
        return $user->isAdmin();
    }

    public function destroy(User $user, Calendar $calendar) 
    {
        return $user->isAdmin();
    }
}
