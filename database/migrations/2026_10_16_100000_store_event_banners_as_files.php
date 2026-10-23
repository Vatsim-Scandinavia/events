<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Banners are uploaded and stored as WebP files on the public disk instead of
     * being linked from elsewhere, so the column now holds a path on that disk.
     */
    public function up(): void
    {
        DB::table('events')->update(['banner_url' => null]);

        Schema::table('events', function (Blueprint $table) {
            $table->renameColumn('banner_url', 'banner_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('events')->update(['banner_path' => null]);

        Schema::table('events', function (Blueprint $table) {
            $table->renameColumn('banner_path', 'banner_url');
        });
    }
};
