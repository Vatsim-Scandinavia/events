<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff can cancel a whole event or a single date. Cancelled ones are kept, with
     * their staffing, so they can be restored.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('repeat_until');
        });

        Schema::table('event_occurrences', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('ends_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('cancelled_at');
        });

        Schema::table('event_occurrences', function (Blueprint $table) {
            $table->dropColumn('cancelled_at');
        });
    }
};
