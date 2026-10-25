<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Control Center's roster as of its last sync, by CID, so people get
     * their Controller roles when they first sign in.
     */
    public function up(): void
    {
        Schema::create('control_center_members', function (Blueprint $table) {
            $table->unsignedBigInteger('cid')->primary();
            $table->string('rating', 3)->nullable();
            $table->json('active_areas');
            $table->json('endorsements');
            $table->timestamp('synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('control_center_members');
    }
};
