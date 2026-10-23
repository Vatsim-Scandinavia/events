<?php

namespace App\Http\Controllers\Admin;

use App\Auth\Roles;
use App\Enums\AssignmentSource;
use App\Http\Controllers\Controller;
use App\Models\Fir;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Gives someone a role, across the hub or in one FIR, or takes one away, one at a time.
 *
 * People can only hand out or take away a role where they manage users: in a FIR they manage
 * users in, or across the hub if they manage users across the hub. The role must allow no more
 * than they can do there, counting their hub-wide roles. Only Administrators can give or take
 * away Administrator, which always applies across the hub, and the hub keeps at least one.
 */
class RoleAssignmentController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'fir_id' => ['nullable', 'integer', Rule::exists('firs', 'id')],
        ], [
            'role_id.*' => 'Pick a role.',
            'fir_id.*' => 'Pick where the role applies.',
        ]);

        $role = Role::query()->with('permissions')->findOrFail($validated['role_id']);
        $fir = isset($validated['fir_id']) ? Fir::query()->findOrFail($validated['fir_id']) : null;
        $this->ensureGrantable($request, $role, $fir);

        if ($fir !== null && Roles::isAdministrator($role)) {
            throw ValidationException::withMessages(['roles' => 'Administrator always applies across the hub.']);
        }

        // Held already, including when someone else gave it a moment ago: the database keeps one copy.
        if (! $user->assignRole($role, $fir)->wasRecentlyCreated) {
            throw ValidationException::withMessages(['roles' => "{$user->name} already has ".Roles::label($role, $fir).'.']);
        }

        Inertia::flash('success', "{$user->name} now has ".Roles::label($role, $fir).'.');

        return redirect("/admin/users/{$user->id}");
    }

    public function destroy(Request $request, User $user, RoleAssignment $roleAssignment): RedirectResponse
    {
        $assignment = $roleAssignment;
        $assignment->load('role.permissions', 'fir');
        $this->ensureGrantable($request, $assignment->role, $assignment->fir);

        if ($assignment->source === AssignmentSource::Automatic) {
            throw ValidationException::withMessages([
                'roles' => "{$user->name} gets {$assignment->role->name} automatically from their rating and subdivision, so it can’t be removed here.",
            ]);
        }

        DB::transaction(function () use ($assignment) {
            // Two admins removing each other at once must not both pass the check below.
            RoleAssignment::query()->whereNull('fir_id')->whereRelation('role', 'name', Roles::ADMINISTRATOR)->lockForUpdate()->get();

            $assignment->delete();

            // Rolled back with the transaction, so the hub is never left without an Administrator.
            $hasAdministrator = RoleAssignment::query()->whereNull('fir_id')->whereRelation('role', 'name', Roles::ADMINISTRATOR)->exists();

            if (Roles::isAdministrator($assignment->role) && ! $hasAdministrator) {
                throw ValidationException::withMessages([
                    'roles' => 'The hub needs at least one Administrator, so this person has to stay one.',
                ]);
            }
        });

        Inertia::flash('success', "{$user->name} no longer has ".Roles::label($assignment->role, $assignment->fir).'.');

        return redirect("/admin/users/{$user->id}");
    }

    private function ensureGrantable(Request $request, Role $role, ?Fir $fir): void
    {
        if (! $request->user()->managesUsersIn($fir)) {
            throw ValidationException::withMessages([
                'roles' => $fir
                    ? 'You can only give or take away roles in FIRs where you manage users.'
                    : 'You can only give or take away roles across the hub if you manage users across the hub.',
            ]);
        }

        if (! Roles::canGrant($request->user(), $role, $fir)) {
            throw ValidationException::withMessages([
                'roles' => "You can’t give or take away {$role->name}, because it allows more than you can do yourself.",
            ]);
        }
    }
}
