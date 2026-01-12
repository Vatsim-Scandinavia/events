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
                $seenCombinations = []; // Track combinations within this chunk

                foreach ($children as $child) {
                    if (empty($child->parent_id) || empty($child->start_date)) {
                        continue;
                    }

                    // Create unique key for this combination
                    $combinationKey = $child->parent_id . '|' . $child->start_date . '|' . $child->end_date;
                    
                    // Skip if we've already processed this combination in this chunk
                    if (isset($seenCombinations[$combinationKey])) {
                        continue;
                    }

                    // Check if an instance already exists in the database (including soft-deleted ones)
                    $existingInstance = DB::table('event_instances')
                        ->where('event_id', $child->parent_id)
                        ->where('start_time', $child->start_date)
                        ->where('end_time', $child->end_date)
                        ->exists();

                    // Only add if it doesn't already exist
                    if (!$existingInstance) {
                        $rows[] = [
                            'event_id'   => $child->parent_id,
                            'start_time' => $child->start_date,
                            'end_time'   => $child->end_date,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                        
                        // Mark this combination as seen
                        $seenCombinations[$combinationKey] = true;
                    }
                }

                if ($rows) {
                    DB::table('event_instances')->insert($rows);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Rollback not implemented: Cannot reliably distinguish backfilled instances
            // from instances created through normal application flow.
        });
    }
};
