<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sign-in is through VATSIM only, so passwords, email verification, "remember me" and
     * password resets are unused. Roles live in role_assignments, so the permission package's
     * per-user role and permission tables have been empty since then.
     */
    public function up(): void
    {
        // Dropping can't be undone, so stop rather than lose any role or permission still held there.
        foreach (['model_has_roles', 'model_has_permissions'] as $name) {
            $table = config("permission.table_names.{$name}");

            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException("{$table} still holds rows, which would be lost. Move them into role_assignments first.");
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['password', 'email_verified_at', 'remember_token']);
        });

        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists(config('permission.table_names.model_has_roles'));
        Schema::dropIfExists(config('permission.table_names.model_has_permissions'));
    }

    /**
     * Brings the columns and tables back, empty.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->string('password')->nullable()->after('email_verified_at');
            $table->rememberToken()->after('password');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        $tables = config('permission.table_names');
        $columns = config('permission.column_names');

        Schema::create($tables['model_has_permissions'], function (Blueprint $table) use ($tables, $columns) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger($columns['model_morph_key']);
            $table->index([$columns['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign('permission_id')->references('id')->on($tables['permissions'])->cascadeOnDelete();
            $table->primary(['permission_id', $columns['model_morph_key'], 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create($tables['model_has_roles'], function (Blueprint $table) use ($tables, $columns) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger($columns['model_morph_key']);
            $table->index([$columns['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign('role_id')->references('id')->on($tables['roles'])->cascadeOnDelete();
            $table->primary(['role_id', $columns['model_morph_key'], 'model_type'], 'model_has_roles_role_model_type_primary');
        });
    }
};
