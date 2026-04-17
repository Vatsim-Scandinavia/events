<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StaffingBooking>
 */
class StaffingBookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'position_id'               => \App\Models\StaffingPosition::factory(),
            'occurrence_id'             => \App\Models\EventOccurrence::factory(),
            'vatsim_cid'                => fake()->numberBetween(800000, 1500000),
            'discord_user_id'           => (string) fake()->numerify('####################'),
            'booked_by_user_id'         => null,
            'control_center_booking_id' => null,
        ];
    }
}
