<?php

use App\Auth\Roles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        $tables = config('permission.table_names');

        // Plain queries, so this keeps working once the per-user permission table is dropped.
        DB::transaction(function () use ($tables) {
            $ids = DB::table($tables['permissions'])->whereIn('name', ['manage-roles', 'manage-settings'])->pluck('id');

            DB::table($tables['role_has_permissions'])->whereIn('permission_id', $ids)->delete();

            if (Schema::hasTable($tables['model_has_permissions'])) {
                DB::table($tables['model_has_permissions'])->whereIn('permission_id', $ids)->delete();
            }

            DB::table($tables['permissions'])->whereIn('id', $ids)->delete();
        });

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
