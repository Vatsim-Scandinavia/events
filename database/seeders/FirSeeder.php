<?php

namespace Database\Seeders;

use App\Models\Fir;
use Illuminate\Database\Seeder;

class FirSeeder extends Seeder
{
    /**
     * The FIRs VATSIM Scandinavia covers, keyed by ICAO code.
     *
     * @var array<string, string>
     */
    public const FIRS = [
        'BIRD' => 'Reykjavik',
        'EFIN' => 'Finland',
        'EKDK' => 'Copenhagen',
        'ENOR' => 'Polaris',
        'ESAA' => 'Sweden',
    ];

    /**
     * Seed the VATSIM Scandinavia FIRs, leaving any that already exist untouched.
     */
    public function run(): void
    {
        foreach (self::FIRS as $icao => $name) {
            Fir::query()->firstOrCreate(['icao' => $icao], ['name' => $name]);
        }
    }
}
