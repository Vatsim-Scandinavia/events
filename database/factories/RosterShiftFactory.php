<?php

namespace Database\Factories;

use App\Models\EventRoster;
use App\Models\RosterShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RosterShift> */
class RosterShiftFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'roster_id' => EventRoster::factory(),
            'name' => fake()->word(),
        ];
    }
}
