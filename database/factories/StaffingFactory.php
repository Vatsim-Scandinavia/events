<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Staffing>
 */
class StaffingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id'           => \App\Models\Event::factory(),
            'discord_channel_id' => (string) fake()->numerify('####################'),
            'discord_message_id' => null,
        ];
    }
}
