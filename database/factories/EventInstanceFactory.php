<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventInstanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(), // Automatically creates a parent event
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(2),
        ];
    }
}