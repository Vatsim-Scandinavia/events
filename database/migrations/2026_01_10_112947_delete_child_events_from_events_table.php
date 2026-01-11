<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('events')->whereNotNull('parent_id')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // IRREVERSIBLE: Child events with parent_id have been permanently deleted.
        // This migration cannot be rolled back as the deleted data cannot be restored.
        // To restore the data, you must restore from a database backup.
        throw new \Exception('This migration is irreversible. Child events have been permanently deleted. Restore from backup if needed.');
    }
};
