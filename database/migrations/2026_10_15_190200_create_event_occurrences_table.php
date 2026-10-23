<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('staffing_mode')->default('none')->after('routes');
            $table->unsignedSmallInteger('interest_opens_hours')->default(336)->after('staffing_mode');
            $table->unsignedSmallInteger('interest_closes_hours')->default(24)->after('interest_opens_hours');
        });

        Schema::create('event_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at');
            $table->dateTime('roster_published_at')->nullable();
            $table->timestamps();
        });

        // Every existing event becomes a single occurrence on its own dates.
        DB::table('events')->orderBy('id')->each(function (object $event) {
            DB::table('event_occurrences')->insert([
                'event_id' => $event->id,
                'starts_at' => $event->starts_at,
                'ends_at' => $event->ends_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_occurrences');

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['staffing_mode', 'interest_opens_hours', 'interest_closes_hours']);
        });
    }
};
