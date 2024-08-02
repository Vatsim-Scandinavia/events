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
            $table->increments('id');
            $table->unsignedInteger('calendar_id');
            $table->string('title');
            $table->string('short_description', 280);
            $table->text('long_description');
            $table->timestamp('start_date');
            $table->timestamp('end_date')->nullable();
            $table->integer('recurrence_interval')->nullable(); // E.g., 1 for every day, 2 for every second day
            $table->string('recurrence_unit')->nullable(); // E.g., 'day', 'week', 'month', 'year'
            $table->timestamp('recurrence_end_date')->nullable(); // New field for recurrence end date
            $table->tinyInteger('published')->default(0); // might do something with this
            $table->string('image')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedInteger('parent_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->foreign('calendar_id')->references('id')->on('calendars')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('parent_id')->references('id')->on('events')->onDelete('cascade');
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
