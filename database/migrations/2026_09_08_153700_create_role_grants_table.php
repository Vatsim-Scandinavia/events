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
        Schema::create('role_grants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('cid')->on('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('scope_id')->storedAs('coalesce(team_id, 0)');
            $table->string('source', 64);
            $table->timestamps();
            $table->unique(['user_id', 'role_id', 'scope_id', 'source']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_grants');
    }
};
