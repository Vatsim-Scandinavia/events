<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Calendar>
 */
class CalendarFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(2),
            'description' => fake()->paragraph(),
            'visibility' => fake()->randomElement(['public', 'private']),
            'created_by' => fake()->numberBetween(10000000, 10000010),
        ];
    }
}
