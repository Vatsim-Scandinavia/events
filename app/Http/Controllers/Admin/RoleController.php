<?php

namespace App\Http\Controllers\Admin;

use App\Auth\Roles;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets Administrators create roles and choose which permissions each one grants. Roles are
 * shared by every FIR, so no one else may change them.
 *
 * Built-in roles can't be renamed or deleted, and Administrator always has every permission.
 */
class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $roles = Role::query()
            ->with('permissions')
            ->withCount(['assignments as users_count' => fn (Builder $query) => $query->select(DB::raw('count(distinct user_id)'))])
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'users' => $role->users_count,
                'permissions' => Roles::permissionLabels($role),
                'builtIn' => Roles::isBuiltIn($role),
                'administrator' => Roles::isAdministrator($role),
            ]),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Roles/Form', [
            'role' => null,
            'permissions' => $this->permissions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request, null);

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);
        $role->syncPermissions($validated['permissions']);

        Inertia::flash('success', "{$role->name} was created.");

        return redirect('/admin/roles');
    }

    public function edit(Request $request, Role $role): Response
    {
        return Inertia::render('Admin/Roles/Form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
                'users' => $role->assignments()->distinct()->count('user_id'),
                'builtIn' => Roles::isBuiltIn($role),
                'administrator' => Roles::isAdministrator($role),
                'canChange' => ! Roles::isAdministrator($role),
            ],
            'permissions' => $this->permissions(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->ensureChangeable($role);
        $validated = $this->validated($request, $role);

        if (Roles::isBuiltIn($role) && $validated['name'] !== $role->name) {
            throw ValidationException::withMessages(['name' => "{$role->name} is built in, so it can’t be renamed."]);
        }

        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions']);

        Inertia::flash('success', "{$role->name} was saved.");

        return redirect('/admin/roles');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->ensureChangeable($role);

        if (Roles::isBuiltIn($role)) {
            throw ValidationException::withMessages(['role' => "{$role->name} is built in, so it can’t be deleted."]);
        }

        $role->delete();

        Inertia::flash('success', "{$role->name} was deleted.");

        return redirect('/admin/roles');
    }

    /**
     * @return array{name: string, permissions: list<string>}
     */
    private function validated(Request $request, ?Role $role): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role)],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::enum(Permission::class)],
        ]);

        $validated['permissions'] = array_values(array_unique($validated['permissions']));

        return $validated;
    }

    private function ensureChangeable(Role $role): void
    {
        if (Roles::isAdministrator($role)) {
            throw ValidationException::withMessages(['role' => 'Administrator is built in and always has every permission.']);
        }
    }

    /**
     * Every permission a role can grant.
     *
     * @return list<array{value: string, label: string, description: string}>
     */
    private function permissions(): array
    {
        return array_map(fn (Permission $permission) => [
            'value' => $permission->value,
            'label' => $permission->label(),
            'description' => $permission->description(),
        ], Permission::cases());
    }
}
