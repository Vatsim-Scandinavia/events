<?php

namespace App\Policies;

use App\Models\Area;
use App\Models\Group;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function index(User $user) 
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model) 
    {
        return $user->isAdmin() || $user->is($model);
    }

    public function viewAccess(User $user) 
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model) 
    {
        return $user->isAdmin();
    }

    public function updateGroup(User $user, User $model) 
    {
        return $user->isAdmin();
    }
}
