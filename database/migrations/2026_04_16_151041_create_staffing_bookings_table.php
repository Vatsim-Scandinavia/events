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
        Schema::create('staffing_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->constrained('staffing_positions')->onDelete('cascade');
            $table->foreignId('occurrence_id')->constrained('event_occurrences')->onDelete('cascade');
            $table->unsignedBigInteger('vatsim_cid')->nullable();
            $table->string('discord_user_id')->nullable();
            $table->foreignId('booked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('control_center_booking_id')->nullable();
            $table->timestamps();

            $table->unique(['position_id', 'occurrence_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staffing_bookings');
    }
};
