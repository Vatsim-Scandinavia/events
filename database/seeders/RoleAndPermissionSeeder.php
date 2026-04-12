<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::findOrCreate('administrator');
        Role::findOrCreate('event-manager');
        Role::findOrCreate('event-team-member');
        Role::findOrCreate('user');

        Permission::findOrCreate('manage events');
        Permission::findOrCreate('manage events created by others');

        Permission::findOrCreate('manage calendars');
        Permission::findOrCreate('view calendars');
        Permission::findOrCreate('view private calendars');

        Permission::findOrCreate('manage users');

        $adminRole = Role::findByName('administrator');
        $adminRole->syncPermissions(Permission::all());

        $eventManagerRole = Role::findByName('event-manager');
        $eventManagerRole->syncPermissions([
            'manage events',
            'manage events created by others',
            'manage calendars',
            'view private calendars',
        ]);

        $eventTeamMemberRole = Role::findByName('event-team-member');
        $eventTeamMemberRole->syncPermissions([
            'manage events',
            'view calendars',
            'view private calendars',
        ]);
    }
}
