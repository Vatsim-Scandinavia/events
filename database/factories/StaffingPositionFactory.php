<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StaffingPosition>
 */
class StaffingPositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $callsigns = ['EKCH_APP', 'ENGM_TWR', 'ESSA_CTR', 'EFHK_GND', 'BIKF_APP'];

        return [
            'section_id'       => \App\Models\StaffingSection::factory(),
            'position_id'      => fake()->randomElement($callsigns),
            'position_name'    => fake()->words(2, true),
            'start_time'       => '10:00:00',
            'end_time'         => '12:00:00',
            'order'            => fake()->numberBetween(0, 10),
            'is_local_booking' => false,
        ];
    }
}
