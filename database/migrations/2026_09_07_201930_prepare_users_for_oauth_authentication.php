<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('users')->exists()) {
            throw new RuntimeException('Existing local accounts must be mapped to verified VATSIM CIDs before this migration can run.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('id', 'cid');
            $table->renameColumn('name', 'name_full');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('cid')->change();
            $table->dropUnique(['email']);
            $table->dropColumn(['password', 'email_verified_at', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
            $table->unsignedSmallInteger('controller_rating');
            $table->string('division', 16)->nullable();
            $table->string('subdivision', 16)->nullable();
            $table->string('oauth_provider')->nullable();
            $table->string('oauth_id')->nullable();
            $table->text('oauth_access_token')->nullable();
            $table->text('oauth_refresh_token')->nullable();
            $table->timestamp('oauth_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('users')->exists()) {
            throw new RuntimeException('OAuth accounts cannot be converted back to local password accounts automatically.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['controller_rating', 'division', 'subdivision', 'oauth_provider', 'oauth_id', 'oauth_access_token', 'oauth_refresh_token', 'oauth_expires_at']);
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->unique('email');
            $table->unsignedBigInteger('cid')->autoIncrement()->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('cid', 'id');
            $table->renameColumn('name_full', 'name');
        });
    }
};
