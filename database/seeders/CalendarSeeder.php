<?php

namespace Database\Seeders;

use App\Models\Calendar;
use Illuminate\Database\Seeder;

class CalendarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Calendar::factory()->create([
            'id' => 1,
            'name' => 'Community Calendar',
            'public' => true,
        ]);

        Calendar::factory()->create([
            'id' => 2,
            'name' => 'Staff Calendar',
            'public' => false,
        ]);
    }
}
