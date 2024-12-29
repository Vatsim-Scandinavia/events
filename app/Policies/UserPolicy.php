<?php

namespace App\Policies;

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
        return $user->isAdmin() || ($user->is($model) && $user->isModeratorOrAbove());
    }

    public function viewAccess(User $user)
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model)
    {
        return $user->isAdmin();
    }
}
