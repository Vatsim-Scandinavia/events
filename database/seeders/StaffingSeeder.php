<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Position;
use App\Models\Staffing;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StaffingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Staffing::factory()->count(5)->create()->each(function ($staffing) {
            $staffing->event()->associate(Event::whereHas('user')->inRandomOrder()->first()->id);
            $staffing->positions()->createMany(
                Position::factory()->count(rand(1, 3))->make()->toArray()
            );
            $staffing->save();
        });
    }
}
