<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id'         => $this->faker->unique()->randomNumber(9, true),
            'email'      => fake()->unique()->safeEmail(),
            'first_name' => fake()->firstName(),
            'last_name'  => fake()->lastName(),
            'last_login' => now(),
        ];
    }
}
