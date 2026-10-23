<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who holds which role, either across the hub or in one FIR (fir_id).
     *
     * Everyone's current roles came from model_has_roles, which can't hold a FIR. They move
     * here as hub-wide assignments, so nobody's access changes.
     */
    public function up(): void
    {
        Schema::create('role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('fir_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'role_id']);
        });

        $type = (new User)->getMorphClass();

        DB::table('role_assignments')->insert(
            DB::table('model_has_roles')->where('model_type', $type)->get()->map(fn (object $row) => [
                'user_id' => $row->model_id,
                'role_id' => $row->role_id,
                'fir_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all()
        );

        DB::table('model_has_roles')->where('model_type', $type)->delete();
    }

    /**
     * Puts the hub-wide assignments back. Assignments for a single FIR can't be kept.
     */
    public function down(): void
    {
        $type = (new User)->getMorphClass();

        DB::table('model_has_roles')->insertOrIgnore(
            DB::table('role_assignments')->whereNull('fir_id')->get()->map(fn (object $row) => [
                'role_id' => $row->role_id,
                'model_type' => $type,
                'model_id' => $row->user_id,
            ])->all()
        );

        Schema::dropIfExists('role_assignments');
    }
};
