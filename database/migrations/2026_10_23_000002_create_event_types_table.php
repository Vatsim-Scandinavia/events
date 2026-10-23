<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. A new Hub starts out with a few common types, which Administrators
     * can rename or remove.
     */
    public function up(): void
    {
        Schema::create('event_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->timestamps();
        });

        $now = now();

        DB::table('event_types')->insert(collect(['Fly-in', 'Regional', 'Special event', 'Training', 'Group flight'])
            ->map(fn (string $name) => ['name' => $name, 'created_at' => $now, 'updated_at' => $now])
            ->all());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_types');
    }
};
