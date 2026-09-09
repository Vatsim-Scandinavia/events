<?php

namespace Database\Factories;

use App\Models\EventRoster;
use App\Models\RosterPosition;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RosterPosition> */
class RosterPositionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'roster_id' => EventRoster::factory()->openInterest(),
            'callsign' => fake()->unique()->regexify('[A-Z]{4}').'_TWR',
        ];
    }
}
