<?php

use App\Auth\Roles;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates the built-in roles and moves everyone flagged as staff onto Event Staff.
     */
    public function up(): void
    {
        Roles::sync();

        $role = DB::table('roles')->where('name', Roles::EVENT_STAFF)->value('id');

        DB::table('model_has_roles')->insertOrIgnore(
            DB::table('users')->where('is_staff', true)->pluck('id')->map(fn (int $id) => [
                'role_id' => $role,
                'model_type' => (new User)->getMorphClass(),
                'model_id' => $id,
            ])->all()
        );

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_staff');
        });
    }

    /**
     * Brings the flag back for anyone holding Event Staff or Admin.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_staff')->default(false)->after('rating');
        });

        $staff = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereIn('roles.name', [Roles::EVENT_STAFF, 'Admin'])
            ->where('model_type', (new User)->getMorphClass())
            ->pluck('model_id');

        DB::table('users')->whereIn('id', $staff)->update(['is_staff' => true]);
    }
};
