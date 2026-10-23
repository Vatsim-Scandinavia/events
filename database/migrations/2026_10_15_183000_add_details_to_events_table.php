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
        Schema::table('events', function (Blueprint $table) {
            $table->text('description')->nullable()->after('type');
            $table->json('routes')->nullable()->after('airports');
            $table->json('positions')->nullable()->after('routes');
            $table->string('booking_url')->nullable()->after('banner_url');
            $table->string('briefing_url')->nullable()->after('booking_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['description', 'routes', 'positions', 'booking_url', 'briefing_url']);
        });
    }
};
