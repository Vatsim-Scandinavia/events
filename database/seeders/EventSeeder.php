<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // Create Normal Events
        Event::factory()->count(5)->create([
            'calendar_id' => rand(1, 2),
        ])->each(function ($event) {
            $event->user()->associate(User::whereHas('groups')->inRandomOrder()->first()->id);
            $event->save();
        });

        // Create Recurring Events
        Event::factory()->count(3)->create([
            'calendar_id' => rand(1, 2),
            'recurrence_interval' => 1, // Example: every 1 week
            'recurrence_unit' => 'week',
            'recurrence_end_date' => now()->addWeeks(5)->format('Y-m-d H:i:s'),
        ])->each(function ($event) {
            $event->user()->associate(User::whereHas('groups')->inRandomOrder()->first()->id);
            $event->save();

            // Generate and save recurrences if the event is recurring
            if ($event->recurrence_interval && $event->recurrence_unit) {
                $recurrences = $event->generateRecurrences();
                $event->children()->saveMany($recurrences);
            }
        });
    }
}
