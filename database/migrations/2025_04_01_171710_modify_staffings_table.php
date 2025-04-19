<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        Schema::table('staffings', function (Blueprint $table) {
            $table->unsignedInteger('id')->change();
            $table->dropColumn('title');
            if (!Schema::hasColumn('staffings', 'event_id')) {
                if (DB::getDriverName() === 'mysql') {
                    $table->unsignedInteger('event_id')->nullable()->after('section_4_title');
                } else {
                    $table->unsignedInteger('event_id')->nullable();
                }
            }
        });

        Schema::table('staffings', function (Blueprint $table) {
            $table->dropColumn('date');
        });

        Schema::table('staffings', function (Blueprint $table) {
            $table->dropColumn('week_int');
        });

        Schema::table('staffings', function (Blueprint $table) {
            $table->dropColumn('restrict_bookings');
        });

        Schema::table('staffings', function (Blueprint $table) {
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
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
        Schema::table('staffings', function (Blueprint $table) {
            $table->string('title')->after('id');
            $table->date('date')->after('title');
            $table->integer('week_int')->after('date');
            $table->integer('restrict_bookings')->after('week_int');
            $table->dropForeign(['event_id']);
            $table->dropColumn('event_id');
        });
    }
};
