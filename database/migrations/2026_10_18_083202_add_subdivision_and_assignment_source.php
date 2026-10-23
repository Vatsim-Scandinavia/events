<?php

use App\Auth\Roles;
use App\Enums\ControllerRating;
use App\Enums\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Booking, showing interest and being rostered now come from the Controller role instead
     * of the rating alone, and the hub can hand Controller out automatically.
     *
     * Users get their VATSIM subdivision, and role assignments remember whether an admin or the
     * automation gave them. Everyone rated S1 or above can book today, so they get Controller
     * (as if given by an admin) and nobody loses access.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('subdivision', 10)->nullable()->after('rating');
        });

        Schema::table('role_assignments', function (Blueprint $table) {
            $table->string('source', 10)->default('manual')->after('fir_id');
        });

        Roles::sync();

        $controller = DB::table('roles')->where('name', Roles::CONTROLLER)->value('id');
        $permission = DB::table('permissions')->where('name', Permission::BookPositions->value)->value('id');

        DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $controller, 'permission_id' => $permission]);

        $alreadyControllers = DB::table('role_assignments')->where('role_id', $controller)->whereNull('fir_id')->pluck('user_id');

        DB::table('role_assignments')->insert(
            DB::table('users')
                ->where('rating', '>=', ControllerRating::S1->value)
                ->whereNotIn('id', $alreadyControllers)
                ->pluck('id')
                ->map(fn (int $id) => [
                    'user_id' => $id,
                    'role_id' => $controller,
                    'fir_id' => null,
                    'source' => 'manual',
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->all()
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Drops the subdivision and the source. Controller assignments stay, and booking goes back
     * to depending on the rating once the code does.
     */
    public function down(): void
    {
        Schema::table('role_assignments', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('subdivision');
        });
    }
};
