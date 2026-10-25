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
        Schema::create('integration_runs', function (Blueprint $table) {
            $table->id();
            $table->string('integration', 64);
            $table->string('feature', 64);
            $table->string('status', 10);
            $table->unsignedInteger('added')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('removed')->default(0);
            $table->text('message')->nullable();
            $table->json('warnings')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->index(['integration', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_runs');
    }
};
