<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The FIRs the vACC covers, each of which owns its events.
     *
     * The FIR codes admins saved on the settings page, or set in VACC_FIRS, become FIRs named
     * after their code for admins to rename, and the old setting is removed.
     */
    public function up(): void
    {
        Schema::create('firs', function (Blueprint $table) {
            $table->id();
            $table->char('icao', 4)->unique();
            $table->string('name', 60);
            $table->timestamps();
        });

        // Read from the environment directly, as the setting is no longer part of the config.
        $codes = collect([
            ...json_decode((string) DB::table('site_settings')->where('key', 'firs')->value('value'), true) ?: [],
            ...explode(',', (string) env('VACC_FIRS')),
        ])
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter(fn (string $code) => preg_match('/^[A-Z]{4}$/', $code) === 1)
            ->unique()
            ->sort()
            ->values();

        DB::table('firs')->insert($codes->map(fn (string $code) => [
            'icao' => $code,
            'name' => $code,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());

        DB::table('site_settings')->where('key', 'firs')->delete();
        Cache::forget('site-settings');
    }

    /**
     * Puts the FIR codes back into the settings.
     */
    public function down(): void
    {
        $codes = DB::table('firs')->orderBy('icao')->pluck('icao');

        if ($codes->isNotEmpty()) {
            DB::table('site_settings')->updateOrInsert(['key' => 'firs'], ['value' => json_encode($codes->all())]);
        }

        Schema::dropIfExists('firs');
        Cache::forget('site-settings');
    }
};
