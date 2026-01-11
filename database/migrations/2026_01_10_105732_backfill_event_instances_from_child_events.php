<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('events')
            ->orderBy('id')
            ->select(['id', 'parent_id', 'start_date', 'end_date'])
            ->chunkById(500, function ($children) {
                $now = now();
                $rows = [];

                foreach ($children as $child) {
                    if (empty($child->parent_id) || empty($child->start_date)) {
                        continue;
                    }

                    $rows[] = [
                        'event_id'   => $child->parent_id,
                        'start_time' => $child->start_date,
                        'end_time'   => $child->end_date,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows) {
                    DB::table('event_instances')->insertOrIgnore($rows);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            //
        });
    }
};
