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
        Schema::create('positions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('callsign');
            $table->bigInteger('booking_id')->nullable();
            $table->bigInteger('discord_user')->nullable();
            $table->integer('section');
            $table->boolean('local_booking')->default(false);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedInteger('staffing_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
