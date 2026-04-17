<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StaffingSection>
 */
class StaffingSectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'staffing_id' => \App\Models\Staffing::factory(),
            'title'       => fake()->words(2, true),
            'order'       => fake()->numberBetween(0, 10),
        ];
    }
}
