<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. A unique index treats every null as different, so fir_key stands in
     * for fir_id with 0 for across the Hub, letting the database refuse a second copy of the
     * same role in the same place.
     */
    public function up(): void
    {
        Schema::create('role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fir_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('fir_key')->storedAs('coalesce(fir_id, 0)');
            $table->string('source', 10)->default('manual');
            $table->timestamps();
            $table->unique(['user_id', 'role_id', 'fir_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_assignments');
    }
};
