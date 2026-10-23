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
        Schema::create('staffing_interest_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staffing_interest_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
        });

        DB::table('staffing_interest_windows')->insertUsing(
            ['staffing_interest_id', 'starts_at', 'ends_at'],
            DB::table('staffing_interests')->select('id', 'available_from', 'available_until'),
        );

        Schema::table('staffing_interests', function (Blueprint $table) {
            $table->dropColumn(['available_from', 'available_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staffing_interests', function (Blueprint $table) {
            $table->dateTime('available_from')->nullable();
            $table->dateTime('available_until')->nullable();
        });

        // Only one window fits the old columns, so keep the span from the first start to the last end.
        DB::table('staffing_interest_windows')
            ->selectRaw('staffing_interest_id, min(starts_at) as starts_at, max(ends_at) as ends_at')
            ->groupBy('staffing_interest_id')
            ->get()
            ->each(fn ($span) => DB::table('staffing_interests')->where('id', $span->staffing_interest_id)
                ->update(['available_from' => $span->starts_at, 'available_until' => $span->ends_at]));

        Schema::dropIfExists('staffing_interest_windows');
    }
};
