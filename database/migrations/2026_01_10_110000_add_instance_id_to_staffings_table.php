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
        Schema::table('staffings', function (Blueprint $table) {
            $table->foreignId('event_instance_id')
                ->after('section_4_title')
                ->constrained()
                ->nullable()
                ->onDelete('cascade');
        });

        // DATA MIGRATION
        DB::table('staffings')
            ->orderBy('id')
            ->chunkById(1000, function ($staffings) {
                foreach ($staffings as $staffing) {
                    $eventInstanceId = DB::table('event_instances')
                        ->where('event_id', $staffing->event_id)
                        ->whereNull('deleted_at') // Only consider non-soft-deleted instances
                        ->orderBy('id') // Get the first non-deleted instance
                        ->value('id');

                    // Only update if we found a valid event instance
                    if ($eventInstanceId) {
                        DB::table('staffings')
                            ->where('id', $staffing->id)
                            ->update(['event_instance_id' => $eventInstanceId]);
                    }
                }
            });

        // Clean up orphaned staffings that don't have a corresponding active event instance
        DB::table('staffings')
            ->leftJoin('event_instances', function ($join) {
                $join->on('staffings.event_id', '=', 'event_instances.event_id')
                     ->whereNull('event_instances.deleted_at'); // Only join with non-deleted instances
            })
            ->whereNull('event_instances.id')
            ->delete();

        // CLEANUP
        Schema::table('staffings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
            
            $table->foreignId('event_instance_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staffings', function (Blueprint $table) {
            //
        });
    }
};
