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
            $table->increments('id');
            $table->text('description');
            $table->bigInteger('channel_id');
            $table->bigInteger('message_id');
            $table->text('section_1_title')->nullable();
            $table->text('section_2_title')->nullable();
            $table->text('section_3_title')->nullable();
            $table->text('section_4_title')->nullable();
            $table->unsignedInteger('event_id');
            $table->timestamps();
        });

        Schema::table('staffings', function (Blueprint $table) {
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
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
