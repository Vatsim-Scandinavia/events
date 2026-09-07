<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'cid' => fake()->unique()->numberBetween(1000000, 9999999),
            'name_full' => fake()->name(),
            'email' => fake()->safeEmail(),
            'controller_rating' => 1,
            'division' => 'EUD',
            'subdivision' => 'SCA',
        ];
    }
}
