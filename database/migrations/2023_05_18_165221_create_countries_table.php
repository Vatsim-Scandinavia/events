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
        Schema::create('area', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        DB::table('area')->insert([
            ['name' => 'Denmark'],
            ['name' => 'Finland'],
            ['name' => 'Iceland'],
            ['name' => 'Norway'],
            ['name' => 'Sweden'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
