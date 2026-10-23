<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. starts_at and ends_at are the first date's, from which the repeat
     * rule lays out the rest; each date is an occurrence.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fir_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('airports');
            $table->json('routes')->nullable();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at');
            $table->string('timezone')->default('UTC');
            $table->string('repeats')->default('none');
            $table->unsignedSmallInteger('repeat_count')->nullable();
            $table->date('repeat_until')->nullable();
            $table->string('staffing_mode')->default('none');
            $table->unsignedSmallInteger('interest_opens_hours')->default(336);
            $table->unsignedSmallInteger('interest_closes_hours')->default(24);
            $table->string('banner_path')->nullable();
            $table->string('booking_url')->nullable();
            $table->string('briefing_url')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
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
