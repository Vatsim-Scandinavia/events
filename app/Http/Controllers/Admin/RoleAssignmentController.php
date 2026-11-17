<?php

namespace App\Http\Controllers\Admin;

use App\Auth\RoleAssignments;
use App\Auth\Roles;
use App\Http\Controllers\Controller;
use App\Models\Fir;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Gives someone a role, across the hub or in one FIR, or takes one away, one at a time.
 *
 * People can only hand out or take away a role where they manage users: in a FIR they manage
 * users in, or across the hub if they manage users across the hub. The role must allow no more
 * than they can do there, counting their hub-wide roles. Only Administrators can give or take
 * away Administrator, which always applies across the hub, and the hub keeps at least one.
 * The rules themselves live in RoleAssignments.
 */
class RoleAssignmentController extends Controller
{
    public function __construct(private RoleAssignments $roleAssignments) {}

    public function store(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'fir_id' => ['nullable', 'integer', Rule::exists('firs', 'id')],
        ], [
            'role_id.*' => 'Pick a role.',
            'fir_id.*' => 'Pick where the role applies.',
        ]);

        $role = Role::query()->findOrFail($validated['role_id']);
        $fir = isset($validated['fir_id']) ? Fir::query()->findOrFail($validated['fir_id']) : null;

        $this->roleAssignments->giveAs($request->user(), $user, $role, $fir);

        Inertia::flash('success', "{$user->name} now has ".Roles::label($role, $fir).'.');

        return redirect("/admin/users/{$user->id}");
    }

    public function destroy(Request $request, User $user, RoleAssignment $roleAssignment): RedirectResponse
    {
        $this->roleAssignments->removeAs($request->user(), $roleAssignment);

        Inertia::flash('success', "{$user->name} no longer has ".Roles::label($roleAssignment->role, $roleAssignment->fir).'.');

        return redirect("/admin/users/{$user->id}");
    }
}
