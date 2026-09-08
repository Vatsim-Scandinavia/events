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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_team_id')->constrained('teams')->restrictOnDelete();
            $table->string('title');
            $table->string('short_description', 500);
            $table->text('description');
            $table->string('timezone');
            $table->string('local_start', 16);
            $table->string('local_end', 16);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('recurrence')->default('none');
            $table->unsignedSmallInteger('recurrence_interval')->default(1);
            $table->smallInteger('monthly_week')->nullable();
            $table->date('recurrence_until')->nullable();
            $table->string('status')->default('draft');
            $table->string('banner_path')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['owner_team_id', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
