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
        // Shifts belong to the event and are stored as minutes from its start, so the
        // same shifts apply to every occurrence.
        Schema::create('position_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_position_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('starts_offset');
            $table->unsignedSmallInteger('ends_offset');
            $table->timestamps();
        });

        // Bookings belong to a single occurrence of the event.
        Schema::create('shift_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_shift_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_occurrence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['position_shift_id', 'event_occurrence_id']);
        });

        Schema::create('staffing_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_occurrence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['event_occurrence_id', 'user_id']);
        });

        // A Staffing Interest's Availability.
        Schema::create('staffing_interest_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staffing_interest_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
        });

        Schema::create('event_position_staffing_interest', function (Blueprint $table) {
            $table->foreignId('staffing_interest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_position_id')->constrained()->cascadeOnDelete();
            $table->primary(['staffing_interest_id', 'event_position_id']);
        });

        Schema::create('roster_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_occurrence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roster_entries');
        Schema::dropIfExists('event_position_staffing_interest');
        Schema::dropIfExists('staffing_interest_windows');
        Schema::dropIfExists('staffing_interests');
        Schema::dropIfExists('shift_bookings');
        Schema::dropIfExists('position_shifts');
    }
};
