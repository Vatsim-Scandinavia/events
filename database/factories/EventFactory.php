<?php

namespace Database\Factories;

use App\Models\Calendar;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'calendar_id' => Calendar::inRandomOrder()->first()?->id ?? Calendar::factory()->create()->id,
            'title' => $this->faker->sentence(1),
            'short_description' => $this->faker->text(280),
            'long_description' => $this->faker->paragraph(),
            'start_date' => now()->addDays(1)->format('Y-m-d H:i:s'),
            'end_date' => now()->addDays(1)->addHours(2)->format('Y-m-d H:i:s'),
            'recurrence_interval' => null,
            'recurrence_unit' => null,
            'recurrence_end_date' => null,
            'user_id' => User::inRandomOrder()->first()?->id ?? User::factory()->create()->id,
        ];
    }
}
