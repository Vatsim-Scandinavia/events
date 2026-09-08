<?php

namespace Database\Seeders;

use App\PermissionName;
use App\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionName::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RoleName::cases() as $name) {
            $role = Role::firstOrCreate(['name' => $name->value, 'guard_name' => 'web', 'team_id' => null]);

            $role->syncPermissions(match ($name) {
                RoleName::Administrator => PermissionName::cases(),
                RoleName::EventCoordinator => [PermissionName::ViewEvents, PermissionName::ManageEvents],
                RoleName::VaccStaff => [PermissionName::ViewEvents],
                RoleName::Controller, RoleName::Pilot => [],
            });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
