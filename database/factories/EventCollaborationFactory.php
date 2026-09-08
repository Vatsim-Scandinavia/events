<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventCollaboration;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EventCollaboration> */
class EventCollaborationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['event_id' => Event::factory(), 'team_id' => Team::factory(), 'accepted_at' => null];
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => ['accepted_at' => now()]);
    }
}
