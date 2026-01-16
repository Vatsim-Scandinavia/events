<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventInstanceFactory extends Factory
{
    public function definition(): array
    {
        // Create event first - this will trigger EventFactory's afterCreating hook
        // which creates an instance with now()->addDay() and now()->addDay()->addHours(2)
        $event = Event::factory()->create();
        
        // Use unique times that are different from EventFactory's default
        // Add random seconds to ensure uniqueness even if called at the same time
        $baseTime = now()->addDay()->addSeconds(rand(10, 3600)); // Add 10 seconds to 1 hour
        $startTime = $baseTime->copy();
        $endTime = $startTime->copy()->addHours(2);
        
        return [
            'event_id' => $event->id,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];
    }
    
    /**
     * Configure the factory to check for existing instances before creating
     */
    public function configure()
    {
        return $this->afterMaking(function (EventInstance $instance) {
            // Check if an instance with the same event_id, start_time, and end_time already exists
            $exists = EventInstance::where('event_id', $instance->event_id)
                ->where('start_time', $instance->start_time->toDateTimeString())
                ->where('end_time', $instance->end_time->toDateTimeString())
                ->exists();
            
            if ($exists) {
                // Instance already exists, modify the times to be unique
                $instance->start_time = $instance->start_time->addSeconds(rand(3601, 7200));
                $instance->end_time = $instance->start_time->copy()->addHours(2);
            }
        });
    }
}