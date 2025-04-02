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
        Schema::table('positions', function (Blueprint $table) {
            $table->dropForeign(['staffing_id']);
        });

        Schema::table('staffings', function (Blueprint $table) {
            $table->unsignedInteger('id')->autoIncrement()->change();
        });

        Schema::table('positions', function (Blueprint $table) {
            $table->foreign('staffing_id')->references('id')->on('staffings')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
