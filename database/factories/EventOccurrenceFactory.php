<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventOccurrence>
 */
class EventOccurrenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => \App\Models\Event::factory(),
            'start_time' => fake()->dateTimeBetween('+1 hour', '+2 hours'),
            'end_time' => fake()->dateTimeBetween('+4 hours', '+6 hours'),
            'status' => fake()->randomElement(['scheduled', 'completed', 'cancelled']),
            'notified_at' => null,
        ];
    }

    public function past(): static
    {
        return $this->state(fn () => [
            'start_time' => fake()->dateTimeBetween('-2 weeks', '-1 day'),
            'end_time' => fake()->dateTimeBetween('-23 hours', '-1 hour'),
        ]);
    }
}
