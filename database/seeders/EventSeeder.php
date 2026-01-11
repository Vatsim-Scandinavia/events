<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\User;
use App\Services\EventService;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class EventSeeder extends Seeder
{
    protected $eventService;

    // Inject the service through the constructor
    public function __construct(EventService $eventService)
    {
        $this->eventService = $eventService;
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Normal (Single) Events
        Event::factory()->count(5)->create([
            'calendar_id' => rand(1, 2),
            'recurrence_interval' => null,
            'recurrence_unit' => null,
        ])->each(function ($event) {
            $this->assignUserAndGenerate($event, now()->addDays(rand(1, 14)));
        });

        // 2. Create Recurring Events
        Event::factory()->count(3)->create([
            'calendar_id' => rand(1, 2),
            'recurrence_interval' => 1,
            'recurrence_unit' => 'week',
            'recurrence_end_date' => now()->addWeeks(8),
        ])->each(function ($event) {
            $this->assignUserAndGenerate($event, now()->addDays(1));
        });
    }

    /**
     * Helper to handle user assignment and service call
     */
    private function assignUserAndGenerate(Event $event, Carbon $startDate)
    {
        // Associate a random user
        $user = User::whereHas('groups')->inRandomOrder()->first();
        if ($user) {
            $event->user_id = $user->id;
            $event->save();
        }

        // Use your service logic! 
        // We pass the data array your service expects
        $this->eventService->generateInstances($event, [
            'start_date' => $startDate->copy()->setHour(19)->setMinute(0),
            'end_date'   => $startDate->copy()->setHour(21)->setMinute(0),
        ]);
    }
}