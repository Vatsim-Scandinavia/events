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
        // DIAGNOSTIC STEP: Check current state and fix invalid data FIRST
        $columnExists = Schema::hasColumn('staffings', 'event_instance_id');
        
        if ($columnExists) {
            // Find ALL invalid event_instance_id values (including soft-deleted instances)
            // This query finds staffings pointing to non-existent OR soft-deleted instances
            $invalidRows = DB::select("
                SELECT s.id, s.event_instance_id, s.event_id
                FROM staffings s
                LEFT JOIN event_instances ei ON s.event_instance_id = ei.id
                WHERE s.event_instance_id IS NOT NULL 
                AND (ei.id IS NULL OR ei.deleted_at IS NOT NULL)
            ");
            
            if (!empty($invalidRows)) {
                // Set all invalid values to NULL
                $invalidIds = collect($invalidRows)->pluck('id')->toArray();
                DB::table('staffings')
                    ->whereIn('id', $invalidIds)
                    ->update(['event_instance_id' => null]);
            }
        }
        
        // Step 1: Drop existing foreign key if it exists
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'staffings' 
            AND COLUMN_NAME = 'event_instance_id'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        
        foreach ($foreignKeys as $fk) {
            // Drop foreign key using raw SQL (dropForeign with array prepends table name incorrectly)
            try {
                DB::statement("ALTER TABLE staffings DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
            } catch (\Exception $e) {
                // Continue - might already be dropped
            }
        }
        
        // Step 2: Ensure column exists (create if not)
        if (!$columnExists) {
            Schema::table('staffings', function (Blueprint $table) {
                $table->unsignedBigInteger('event_instance_id')
                    ->after('section_4_title')
                    ->nullable();
            });
        }

        // Step 3: Migration of data from event_id to event_instance_id
        DB::table('staffings')
            ->orderBy('id')
            ->chunkById(1000, function ($staffings) {
                foreach ($staffings as $staffing) {
                    // Always check and remigrate if needed
                    $needsMigration = true;
                    
                    if ($staffing->event_instance_id) {
                        // Verify the instance exists and is not soft-deleted
                        $exists = DB::table('event_instances')
                            ->where('id', $staffing->event_instance_id)
                            ->whereNull('deleted_at')
                            ->exists();
                        
                        if ($exists) {
                            $needsMigration = false;
                        } else {
                            // Invalid - set to NULL so we can remigrate
                            DB::table('staffings')
                                ->where('id', $staffing->id)
                                ->update(['event_instance_id' => null]);
                        }
                    }
                    
                    if ($needsMigration) {
                        $eventInstanceId = DB::table('event_instances')
                            ->where('event_id', $staffing->event_id)
                            ->whereNull('deleted_at')
                            ->orderBy('start_time')
                            ->orderBy('id')
                            ->value('id');

                        if ($eventInstanceId) {
                            DB::table('staffings')
                                ->where('id', $staffing->id)
                                ->update(['event_instance_id' => $eventInstanceId]);
                        }
                    }
                }
            });

        // Step 4: Clean up orphaned staffings (those with no matching event instances)
        $unmatchedCount = DB::table('staffings')
            ->whereNull('event_instance_id')
            ->count();

        if ($unmatchedCount > 0) {
            DB::table('staffings')
                ->leftJoin('event_instances', function ($join) {
                    $join->on('staffings.event_id', '=', 'event_instances.event_id')
                        ->whereNull('event_instances.deleted_at');
                })
                ->whereNull('event_instances.id')
                ->delete();
            
            // Verify that no NULLs remain
            $remainingNulls = DB::table('staffings')
                ->whereNull('event_instance_id')
                ->count();

            if ($remainingNulls > 0) {
                throw new \RuntimeException(
                    "Cannot proceed: {$remainingNulls} staffing(s) still have NULL event_instance_id. " .
                    "These must be handled before making the column non-nullable."
                );
            }
        }
        
        // Step 5: FINAL validation - check for ANY invalid data
        // This must pass before we add the constraint
        $invalidData = DB::select("
            SELECT s.id, s.event_instance_id
            FROM staffings s
            LEFT JOIN event_instances ei ON s.event_instance_id = ei.id AND ei.deleted_at IS NULL
            WHERE s.event_instance_id IS NOT NULL 
            AND ei.id IS NULL
        ");
        
        if (!empty($invalidData)) {
            $ids = collect($invalidData)->pluck('id')->take(10)->implode(', ');
            $count = count($invalidData);
            throw new \RuntimeException(
                "CRITICAL: Found {$count} staffing(s) with invalid event_instance_id values. " .
                "Example IDs: {$ids}. " .
                "Cannot add foreign key constraint. Please investigate these records manually."
            );
        }
        
        // Step 6: Add foreign key constraint (data is now guaranteed valid)
        // Check if foreign key already exists before adding
        $existingFk = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'staffings' 
            AND COLUMN_NAME = 'event_instance_id'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        
        if (empty($existingFk)) {
            Schema::table('staffings', function (Blueprint $table) {
                $table->foreign('event_instance_id')
                    ->references('id')
                    ->on('event_instances')
                    ->onDelete('cascade');
            });
        }
        
        // Step 7: Clean-up - drop old event_id and make new column non-nullable
        Schema::table('staffings', function (Blueprint $table) {
            if (Schema::hasColumn('staffings', 'event_id')) {
                $table->dropConstrainedForeignId('event_id');
            }
            $table->unsignedBigInteger('event_instance_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staffings', function (Blueprint $table) {
            throw new \RuntimeException(
                'This migration is not reversible. Staffing records without event instances were deleted. '
                . 'Restore from a pre-migration backup if rollback is needed.'
            );
        });
    }
};
