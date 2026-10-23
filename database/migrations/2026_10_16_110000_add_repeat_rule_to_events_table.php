<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Events repeat by a rule from their first date, which the event's own start and
     * end now always describe.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('repeats')->default('none')->after('ends_at');
            $table->unsignedSmallInteger('repeat_count')->nullable()->after('repeats');
            $table->date('repeat_until')->nullable()->after('repeat_count');
        });

        DB::table('events')->orderBy('id')->each(function (object $event) {
            $first = DB::table('event_occurrences')->where('event_id', $event->id)->orderBy('starts_at')->first();

            if ($first) {
                DB::table('events')->where('id', $event->id)->update(['starts_at' => $first->starts_at, 'ends_at' => $first->ends_at]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['repeats', 'repeat_count', 'repeat_until']);
        });
    }
};
