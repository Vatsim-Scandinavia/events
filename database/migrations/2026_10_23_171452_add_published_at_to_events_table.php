<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Events start as Drafts, hidden until staff publish them. Events made before Drafts
     * existed were already public, so they count as published when they were created.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('cancelled_at');
        });

        DB::table('events')->update(['published_at' => DB::raw('coalesce(created_at, current_timestamp)')]);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });
    }
};
