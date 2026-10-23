<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The time zone staff schedule an event in, so its dates keep their local time
     * across daylight saving changes. Existing events stay in UTC.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('timezone')->default('UTC')->after('ends_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
