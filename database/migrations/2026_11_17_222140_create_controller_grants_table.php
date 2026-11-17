<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. A Controller Grant says an Account may staff events in one FIR, or
     * Hub-wide when there's no FIR. As with role assignments, fir_key stands in for fir_id with 0
     * for Hub-wide, so the database refuses a second grant in the same place, whoever gave it.
     *
     * Deleting a FIR is restricted, not cascaded, for the same reason as role assignments: Fir
     * removes its grants itself.
     */
    public function up(): void
    {
        Schema::create('controller_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fir_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('fir_key')->storedAs('coalesce(fir_id, 0)');
            $table->string('origin', 10);
            $table->timestamps();
            $table->unique(['user_id', 'fir_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('controller_grants');
    }
};
