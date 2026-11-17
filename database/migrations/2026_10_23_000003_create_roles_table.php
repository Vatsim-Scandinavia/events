<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations, creating the roles every Hub has. Administrator holds every permission
     * by rule, so it stores none; the others start out with a few, which admins can change.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->json('permissions');
            $table->timestamps();
        });

        $now = now();

        DB::table('roles')->insert(collect([
            'Administrator' => [],
            'Event Staff' => ['manage-events', 'manage-staffing'],
            'vACC Staff' => ['view-events'],
            'Controller' => ['book-positions'],
        ])->map(fn (array $permissions, string $name) => [
            'name' => $name,
            'permissions' => json_encode($permissions),
            'created_at' => $now,
            'updated_at' => $now,
        ])->values()->all());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
