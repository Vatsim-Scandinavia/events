<?php

namespace App\Policies;

use App\Models\Staffing;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class StaffingPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Staffing $staffing)
    {
        return $user->isAdmin() || $user->isModerator();
    }
}
