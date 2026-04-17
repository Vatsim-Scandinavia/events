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
        Schema::create('staffing_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('staffing_sections')->onDelete('cascade')->index();
            $table->string('position_id');
            $table->string('position_name');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('order')->default(0);
            $table->boolean('is_local_booking')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staffing_positions');
    }
};
