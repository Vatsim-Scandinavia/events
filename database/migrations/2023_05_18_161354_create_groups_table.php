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
        Schema::create('groups', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->text('description');
        });

        DB::table('groups')->insert([
            ['id' => 1, 'name' => 'Administrator', 'description' => 'Access to whole system. Ment for vACC DIR, Event coordinator and technicians.'],
            ['id' => 2, 'name' => 'Moderator', 'description' => 'Access to key features of the system. Ment for Event Assistants and other event related duties.'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
