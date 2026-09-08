<?php

namespace Database\Factories;

use App\Models\Airport;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Airport> */
class AirportFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['icao' => strtoupper(fake()->unique()->lexify('????')), 'name' => fake()->city().' Airport', 'country' => fake()->country()];
    }
}
