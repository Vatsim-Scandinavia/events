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
            $table->foreignId('calendar_id')->constrained('calendars')->onDelete('cascade');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('short_description')->nullable();
            $table->text('long_description')->nullable();
            $table->json('featured_airports')->nullable();
            $table->string('banner_path')->nullable();
            $table->enum('status', ['draft', 'published', 'cancelled'])->default('draft');
            $table->string('recurrence_rule')->nullable();
            $table->string('timezone')->default('UTC');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->softDeletes();
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
