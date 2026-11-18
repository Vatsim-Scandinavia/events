<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. One token pair per Account, the newest the Sign-in Provider issued it
     * (Handover revokes a member's older access tokens whenever it issues one). Tokens are stored
     * encrypted, so they are text rather than strings, and never kept in the session.
     */
    public function up(): void
    {
        Schema::create('sign_in_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sign_in_tokens');
    }
};
