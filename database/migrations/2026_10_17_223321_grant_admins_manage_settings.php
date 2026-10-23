<?php

use App\Auth\Roles;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Creates the manage-settings permission and gives it to Admin, which holds everything.
     */
    public function up(): void
    {
        Roles::sync();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
