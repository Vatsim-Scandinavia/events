<?php

namespace App\Policies;

use App\Models\Staffing;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class StaffingPolicy
{
    use HandlesAuthorization;

    public function index(User $user)
    {
        return $user->isAdmin() || $user->isModerator();
    }

    public function view(User $user, Staffing $staffing)
    {
        return $user->isAdmin() || $user->isModerator();
    }

    public function create(User $user) 
    {
        return $user->isAdmin() || $user->isModerator();
    }

    public function update(User $user, Staffing $staffing)
    {
        return $user->isAdmin() || $user->isModerator();
    }

    public function destroy(User $user, Staffing $staffing)
    {
        return $user->isAdmin() || $user->isModerator();
    }
}
