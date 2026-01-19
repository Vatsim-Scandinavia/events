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
            $table->foreignId('staffing_id')->constrained()->onDelete('cascade');
            $table->string('position_id');
            $table->string('position_name');
            $table->integer('order')->default(0);
            $table->foreignId('booked_by_user_id')->nullable()->constrained('users')->onDelete('set null');
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
