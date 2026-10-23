<?php

use App\Auth\Roles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Admin becomes Administrator, keeping its holders, and the vACC Staff and Controller
     * roles and the view-events permission are added.
     */
    public function up(): void
    {
        if (! DB::table('roles')->where('name', Roles::ADMINISTRATOR)->exists()) {
            DB::table('roles')->where('name', 'Admin')->update(['name' => Roles::ADMINISTRATOR]);
        }

        Roles::sync();
    }

    /**
     * Gives Administrator its old name back. The added roles stay, like roles admins made.
     */
    public function down(): void
    {
        DB::table('roles')->where('name', Roles::ADMINISTRATOR)->update(['name' => 'Admin']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
