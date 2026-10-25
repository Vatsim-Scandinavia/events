<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. One row per Integration an admin has set up, keyed by the
     * Integration's key. Settings are stored encrypted, as they hold API tokens.
     */
    public function up(): void
    {
        Schema::create('integration_settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->boolean('enabled')->default(false);
            $table->json('features')->nullable();
            $table->text('settings')->nullable();
            $table->unsignedTinyInteger('removal_limit')->default(25);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_settings');
    }
};
