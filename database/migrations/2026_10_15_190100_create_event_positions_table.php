<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Positions move out of the events JSON column into their own table so shifts,
     * interest and rosters can reference them.
     */
    public function up(): void
    {
        Schema::create('event_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('callsign');
            $table->string('frequency')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['event_id', 'callsign']);
        });

        DB::table('events')->whereNotNull('positions')->orderBy('id')->each(function (object $event) {
            foreach (array_values(json_decode($event->positions, true) ?? []) as $sort => $position) {
                DB::table('event_positions')->insert([
                    'event_id' => $event->id,
                    'callsign' => $position['callsign'],
                    'frequency' => $position['frequency'] ?? null,
                    'sort' => $sort,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('positions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->json('positions')->nullable()->after('routes');
        });

        DB::table('event_positions')->orderBy('sort')->get()->groupBy('event_id')->each(function ($positions, $eventId) {
            DB::table('events')->where('id', $eventId)->update([
                'positions' => json_encode($positions->map(fn (object $position) => [
                    'callsign' => $position->callsign,
                    'frequency' => $position->frequency,
                ])->values()),
            ]);
        });

        Schema::dropIfExists('event_positions');
    }
};
