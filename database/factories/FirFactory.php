<?php

namespace Database\Factories;

use App\Models\Fir;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fir>
 */
class FirFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'icao' => fake()->unique()->regexify('[A-Z]{4}'),
            'name' => fake()->unique()->city(),
        ];
    }
}
