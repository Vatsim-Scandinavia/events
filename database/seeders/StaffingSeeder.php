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
        $event = Event::has('calendar')->inRandomOrder()->first();

        if (!$event) {
            $this->command->warn('No events with calendars found. Skipping StaffingSeeder.');
            return;
        }
        
        Staffing::factory()->count(5)->create([
            'event_id' => $event->id,
        ])->each(function ($staffing) {
            $staffing->positions()->createMany(
                Position::factory()->count(rand(1, 3))->make()->toArray()
            );
            $staffing->save();
        });
    }
}
