<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'calendar_id' => \App\Models\Calendar::factory(),
            'title' => $this->faker->sentence(3),
            'slug' => $this->faker->slug(),
            'short_description' => $this->faker->sentence(),
            'long_description' => $this->faker->paragraph(),
            'featured_airports' => $this->faker->randomElements(['EKCH', 'ENGM', 'ESSA', 'EFHK', 'BIKF'], 2),
            'banner_path' => null,
            'status' => $this->faker->randomElement(['draft', 'published', 'cancelled']),
            'recurrence_rule' => null,
            'timezone' => $this->faker->timezone(),
            'created_by' => \App\Models\User::factory(),
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => 'published']);
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }

    public function recurring(string $rule = 'FREQ=WEEKLY'): static
    {
        return $this->state(['recurrence_rule' => $rule]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (\App\Models\Event $event) {
            // Always create one past occurrence
            \App\Models\EventOccurrence::factory()
                ->past()
                ->create(['event_id' => $event->id]);

            // Create 0-2 additional future occurrences
            \App\Models\EventOccurrence::factory()
                ->count(rand(0, 2))
                ->create(['event_id' => $event->id]);
        });
    }
}
