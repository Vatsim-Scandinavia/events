<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_rosters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->date('occurrence_date');
            $table->string('mode');
            $table->boolean('is_open')->default(false);
            $table->dateTime('opened_at')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'occurrence_date']);
        });
        Schema::create('roster_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('roster_id')->constrained('event_rosters')->cascadeOnDelete();
            $table->string('name', 100);
            $table->timestamps();
        });
        Schema::create('roster_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shift_id')->constrained('roster_shifts')->cascadeOnDelete();
            $table->string('callsign', 30);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedBigInteger('booked_by')->nullable();
            $table->foreign('booked_by')->references('cid')->on('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['shift_id', 'callsign']);
            $table->index(['booked_by', 'starts_at', 'ends_at']);
        });
        Schema::create('roster_positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('roster_id')->constrained('event_rosters')->cascadeOnDelete();
            $table->string('callsign', 30);
            $table->timestamps();
            $table->unique(['roster_id', 'callsign']);
        });
        Schema::create('roster_interests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('roster_id')->constrained('event_rosters')->cascadeOnDelete();
            $table->unsignedBigInteger('user_cid');
            $table->foreign('user_cid')->references('cid')->on('users')->restrictOnDelete();
            $table->json('position_ids');
            $table->json('availability');
            $table->timestamps();
            $table->unique(['roster_id', 'user_cid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roster_interests');
        Schema::dropIfExists('roster_positions');
        Schema::dropIfExists('roster_slots');
        Schema::dropIfExists('roster_shifts');
        Schema::dropIfExists('event_rosters');
    }
};
