<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventRoster;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EventRoster> */
class EventRosterFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'mode' => 'pre_slotted',
            'is_open' => false,
            'opened_at' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (): array => ['is_open' => true, 'opened_at' => now()]);
    }

    public function openInterest(): static
    {
        return $this->state(fn (): array => ['mode' => 'open_interest']);
    }
}
