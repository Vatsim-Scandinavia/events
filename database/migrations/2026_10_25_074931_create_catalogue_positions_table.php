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
        Schema::create('catalogue_positions', function (Blueprint $table) {
            $table->id();
            $table->string('callsign', 20)->unique();
            $table->string('name', 100)->nullable();
            $table->string('frequency', 7)->nullable();
            $table->string('integration', 64)->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalogue_positions');
    }
};
