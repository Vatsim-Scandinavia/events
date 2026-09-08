<?php

namespace App\Http\Controllers;

use App\Actions\Authorization\UpdateRoleAssignments;
use App\Http\Requests\UserRoleRequest;
use App\Models\Team;
use App\Models\User;
use App\RoleName;
use Illuminate\Http\Response;

class UserRoleController extends Controller
{
    public function store(UserRoleRequest $request, User $user, UpdateRoleAssignments $assignments): Response
    {
        $assignments->grant($user, RoleName::from($request->validated('role')), $this->team($request));

        return response()->noContent();
    }

    public function destroy(UserRoleRequest $request, User $user, UpdateRoleAssignments $assignments): Response
    {
        $assignments->revoke($user, RoleName::from($request->validated('role')), $this->team($request));

        return response()->noContent();
    }

    private function team(UserRoleRequest $request): ?Team
    {
        return $request->validated('team_id') === null ? null : Team::findOrFail($request->integer('team_id'));
    }
}
