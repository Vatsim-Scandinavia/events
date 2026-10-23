<?php

use App\Models\EventType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Event types become a list admins manage instead of free text.
     *
     * The old suggestions start the list. Events whose type matches one of them keep it;
     * the rest have no type and show as General.
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

        Schema::table('events', function (Blueprint $table) {
            $table->foreignIdFor(EventType::class)->nullable()->after('type')->constrained()->nullOnDelete();
        });

        DB::table('event_types')->get()->each(fn (object $type) => DB::table('events')
            ->whereRaw('lower(type) = ?', [strtolower($type->name)])
            ->update(['event_type_id' => $type->id]));

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('type')->default('General')->after('name');
        });

        DB::table('event_types')->get()->each(fn (object $type) => DB::table('events')
            ->where('event_type_id', $type->id)
            ->update(['type' => $type->name]));

        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignIdFor(EventType::class);
        });

        Schema::dropIfExists('event_types');
    }
};
