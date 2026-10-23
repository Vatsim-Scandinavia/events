<?php

use App\Auth\Roles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Roles and the hub's settings, FIRs and event types are shared by every FIR, so they're
     * now the Administrator's alone. The permissions that let other roles manage them go, and
     * every role that held them loses them.
     */
    public function up(): void
    {
        DB::transaction(fn () => Permission::query()->whereIn('name', ['manage-roles', 'manage-settings'])->get()->each->delete());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Brings the permissions back for Administrator only. Other roles that held them don't
     * get them back.
     */
    public function down(): void
    {
        DB::transaction(function () {
            foreach (['manage-roles', 'manage-settings'] as $name) {
                Permission::findOrCreate($name, 'web')->assignRole(Roles::ADMINISTRATOR);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
