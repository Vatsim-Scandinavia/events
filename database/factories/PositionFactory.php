<?php

namespace Database\Factories;

use App\Models\Staffing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Position>
 */
class PositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'callsign' => $this->faker->unique()->word(),
            'booking_id' => $this->faker->randomNumber(),
            'discord_user' => $this->faker->randomNumber(),
            'section' => rand(1, 4),
            'local_booking' => rand(0, 1),
            'start_time' => now()->format('H:i'),
            'end_time' => now()->addHours(2)->format('H:i'),
            'staffing_id' => Staffing::factory()->create()->id,
        ];
    }
}
