<?php

namespace Database\Seeders;

use App\Models\EventInstance;
use App\Models\Position;
use App\Models\Staffing;
use Illuminate\Database\Seeder;

class StaffingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get instances that don't have staffing yet
        $instances = EventInstance::whereDoesntHave('staffing')->inRandomOrder()->take(10)->get();

        if ($instances->isEmpty()) {
            $this->command->warn('No event instances found. Run EventSeeder first!');
            return;
        }

        foreach ($instances as $instance) {
            // Create exactly one Staffing record per Instance
            $staffing = Staffing::factory()->create([
                'event_instance_id' => $instance->id,
            ]);

            // Add some positions to the staffing
            $staffing->positions()->createMany(
                Position::factory()->count(rand(2, 5))->make()->toArray()
            );
        }
    }
}