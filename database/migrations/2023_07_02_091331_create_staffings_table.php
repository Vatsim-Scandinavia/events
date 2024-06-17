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
        Schema::create('staffings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('date');
            $table->text('description');
            $table->bigInteger('channel_id');
            $table->bigInteger('message_id')->nullable();
            $table->integer('week_int');
            $table->text('section_1_title');
            $table->text('section_2_title')->nullable();
            $table->text('section_3_title')->nullable();
            $table->text('section_4_title')->nullable();
            $table->integer('restrict_bookings');
            $table->unsignedBigInteger('area_id');
            $table->timestamps();
        });

        Schema::table('staffings', function (Blueprint $table) {
            $table->foreign('area_id')->references('id')->on('area')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staffings');
    }
};
