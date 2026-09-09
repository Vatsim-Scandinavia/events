<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->boolean('roster_enabled')->default(false);
        });

        DB::table('events')->whereIn('id', DB::table('event_rosters')->select('event_id'))
            ->update(['roster_enabled' => true]);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('roster_enabled');
        });
    }
};
