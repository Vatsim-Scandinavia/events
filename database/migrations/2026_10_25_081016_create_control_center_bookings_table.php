<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. What the Hub sent Control Center as bookings, by the Shift Booking or
     * Roster Entry it came from. Kept apart from them, without foreign keys, so a record outlives
     * a deleted booking long enough to delete it upstream too.
     */
    public function up(): void
    {
        Schema::create('control_center_bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_occurrence_id')->index();
            $table->string('source', 16);
            $table->unsignedBigInteger('source_id');
            $table->unsignedBigInteger('upstream_id')->nullable();
            $table->unsignedBigInteger('cid')->nullable();
            $table->string('callsign', 20);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 10);
            $table->text('reason')->nullable();
            $table->boolean('retryable')->default(false);
            $table->timestamps();
            $table->unique(['source', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('control_center_bookings');
    }
};
