<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Each Event's Roster channel webhook and the Roster message posted
     * through it. Kept without a foreign key, so a record outlives a deleted Event long enough
     * to delete its message in Discord too.
     */
    public function up(): void
    {
        Schema::create('discord_roster_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->unique();
            $table->text('webhook');
            $table->string('channel_id', 32);
            $table->string('webhook_name', 100)->nullable();
            $table->string('message_id', 32)->nullable();
            $table->string('rendered_hash', 64)->nullable();
            $table->text('failure')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discord_roster_messages');
    }
};
