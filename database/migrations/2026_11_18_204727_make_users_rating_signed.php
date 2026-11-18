<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. VATSIM rates a suspended member -1, which an unsigned column turns away
     * on MySQL, so a suspension could never be saved there.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->tinyInteger('rating')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations. A negative rating can't be kept, so it becomes 0, also turned away at sign-in.
     */
    public function down(): void
    {
        DB::table('users')->where('rating', '<', 0)->update(['rating' => 0]);

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->default(0)->change();
        });
    }
};
