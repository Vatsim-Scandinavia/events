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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('vatsim_cid')->nullable()->unique()->after('id');
            $table->unsignedTinyInteger('rating')->default(0)->after('email');
            $table->boolean('is_staff')->default(false)->after('rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['vatsim_cid']);
            $table->dropColumn(['vatsim_cid', 'rating', 'is_staff']);
        });
    }
};
