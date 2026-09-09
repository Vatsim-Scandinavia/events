<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Event> */
class EventFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'owner_team_id' => Team::factory(),
            'title' => fake()->sentence(4),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'timezone' => 'UTC',
            'local_start' => '2026-10-04T18:00',
            'local_end' => '2026-10-04T21:00',
            'starts_at' => '2026-10-04 18:00:00',
            'ends_at' => '2026-10-04 21:00:00',
            'recurrence' => 'none',
            'recurrence_interval' => 1,
            'status' => 'draft',
            'roster_enabled' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => 'published', 'published_at' => now()]);
    }

    public function rostered(): static
    {
        return $this->state(fn (): array => ['roster_enabled' => true]);
    }

    public function weekly(int $interval = 1): static
    {
        return $this->state(fn (): array => ['recurrence' => 'weekly', 'recurrence_interval' => $interval]);
    }

    public function monthly(int $interval = 1, int $week = 1): static
    {
        return $this->state(fn (): array => ['recurrence' => 'monthly', 'recurrence_interval' => $interval, 'monthly_week' => $week]);
    }
}
