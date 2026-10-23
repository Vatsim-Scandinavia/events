<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nobody can hold the same role twice in the same place, even when two people give it at
     * once. A unique index on fir_id alone wouldn't do, as databases treat its nulls (across the
     * hub) as all different, so fir_key holds the FIR's id, or 0 across the hub. RoleAssignment
     * keeps it in step with fir_id.
     */
    public function up(): void
    {
        Schema::table('role_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('fir_key')->default(0)->after('fir_id');
        });

        DB::table('role_assignments')->whereNotNull('fir_id')->update(['fir_key' => DB::raw('fir_id')]);

        $this->mergeDuplicates();

        Schema::table('role_assignments', function (Blueprint $table) {
            $table->unique(['user_id', 'role_id', 'fir_key']);
        });
    }

    public function down(): void
    {
        Schema::table('role_assignments', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'role_id', 'fir_key']);
            $table->dropColumn('fir_key');
        });
    }

    /**
     * Keeps one assignment per person, role and place. If any copy was given by hand, the kept
     * one counts as given by hand, so the automatic Controller role can't take it away.
     */
    private function mergeDuplicates(): void
    {
        DB::table('role_assignments')
            ->select('user_id', 'role_id', 'fir_key')
            ->groupBy('user_id', 'role_id', 'fir_key')
            ->havingRaw('count(*) > 1')
            ->get()
            ->each(function (object $place) {
                $copies = DB::table('role_assignments')
                    ->where(['user_id' => $place->user_id, 'role_id' => $place->role_id, 'fir_key' => $place->fir_key])
                    ->orderBy('id')
                    ->get();
                $kept = $copies->first();

                if ($copies->contains('source', 'manual')) {
                    DB::table('role_assignments')->where('id', $kept->id)->update(['source' => 'manual']);
                }

                DB::table('role_assignments')->whereIn('id', $copies->skip(1)->pluck('id'))->delete();
            });
    }
};
