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
        Schema::table('staffings', function (Blueprint $table) {
            $table->foreignId('event_instance_id')
                ->after('section_4_title')
                ->constrained()
                ->nullable()
                ->onDelete('cascade');
        });

        // DATA MIGRATION
        $staffings = DB::table('staffings')->get();
        foreach ($staffings as $staffing) {
            $eventInstanceId = DB::table('event_instances')
                ->where('event_id', $staffing->event_id)
                ->value('id');

            DB::table('staffings')
                ->where('id', $staffing->id)
                ->update(['event_instance_id' => $eventInstanceId]);
        }

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
