<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            ->chunkById(500, function ($events) {
                $now = now();
                $rows = [];

                foreach ($events as $event) {
                    if ($event->parent_id || empty($event->start_date)) {
                        continue;
                    }

                    $rows[] = [
                        'event_id'   => $event->id,
                        'start_time' => $event->start_date,
                        'end_time'   => $event->end_date,
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
