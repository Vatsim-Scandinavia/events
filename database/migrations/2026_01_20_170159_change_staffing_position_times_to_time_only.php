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
        // For MySQL, we need to create new columns, copy data, drop old, rename
        // For SQLite in testing, we can use ALTER COLUMN
        
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            // MySQL approach: create temp columns, copy data, drop old, rename
            Schema::table('staffing_positions', function (Blueprint $table) {
                $table->time('start_time_new')->nullable()->after('end_time');
                $table->time('end_time_new')->nullable()->after('start_time_new');
            });
            
            // Copy time portion from datetime to new time columns
            DB::statement('UPDATE staffing_positions SET start_time_new = TIME(start_time) WHERE start_time IS NOT NULL');
            DB::statement('UPDATE staffing_positions SET end_time_new = TIME(end_time) WHERE end_time IS NOT NULL');
            
            // Drop old columns and rename new ones
            Schema::table('staffing_positions', function (Blueprint $table) {
                $table->dropColumn(['start_time', 'end_time']);
            });
            
            Schema::table('staffing_positions', function (Blueprint $table) {
                $table->renameColumn('start_time_new', 'start_time');
                $table->renameColumn('end_time_new', 'end_time');
            });
        } else {
            // SQLite approach: simpler but requires recreating table
            // Extract time portion before changing column type
            DB::statement('UPDATE staffing_positions SET start_time = TIME(start_time) WHERE start_time IS NOT NULL');
            DB::statement('UPDATE staffing_positions SET end_time = TIME(end_time) WHERE end_time IS NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            // Reverse: time back to datetime
            Schema::table('staffing_positions', function (Blueprint $table) {
                $table->dateTime('start_time_new')->nullable()->after('end_time');
                $table->dateTime('end_time_new')->nullable()->after('start_time_new');
            });
            
            // Copy time data (will lose date portion - acceptable for rollback)
            DB::statement('UPDATE staffing_positions SET start_time_new = start_time WHERE start_time IS NOT NULL');
            DB::statement('UPDATE staffing_positions SET end_time_new = end_time WHERE end_time IS NOT NULL');
            
            Schema::table('staffing_positions', function (Blueprint $table) {
                $table->dropColumn(['start_time', 'end_time']);
            });
            
            Schema::table('staffing_positions', function (Blueprint $table) {
                $table->renameColumn('start_time_new', 'start_time');
                $table->renameColumn('end_time_new', 'end_time');
            });
        }
    }
};
