<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventCancellation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EventCancellation> */
class EventCancellationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['event_id' => Event::factory(), 'occurrence_date' => '2026-10-04', 'reason' => 'Insufficient staffing'];
    }
}
