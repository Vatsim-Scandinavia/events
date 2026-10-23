<?php

use App\Models\Fir;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every event belongs to the FIR that runs it.
     *
     * With a single FIR, existing events are given to it. With several, nobody can tell which
     * FIR runs which event, so the column stays empty until staff pick one on the next save.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignIdFor(Fir::class)->nullable()->after('id')->constrained()->restrictOnDelete();
        });

        $firs = DB::table('firs')->pluck('id');

        if ($firs->count() === 1) {
            DB::table('events')->update(['fir_id' => $firs->sole()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignIdFor(Fir::class);
        });
    }
};
