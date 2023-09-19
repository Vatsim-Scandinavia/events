<?php

namespace Database\Factories;

use App\Models\Area;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Staffing>
 */
class StaffingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $areas = collect(Area::all()->modelKeys());

        return [
            'title' => fake()->sentence(),
            'date' => fake()->dateTimeBetween('+1 month', '+6 month'),
            'description' => fake()->paragraph(),
            'channel_id' => $this->ranNumbers(),
            'message_id' => $this->ranNumbers(),
            'week_int' => rand(1, 4),
            'section_1_title' => fake()->sentence(),
            'section_2_title' => fake()->sentence(),
            'section_3_title' => fake()->sentence(),
            'section_4_title' => fake()->sentence(),
            'restrict_bookings' => rand(0, 1),
            'area_id' => $areas->random(),
        ];
    }

    function ranNumbers() {
        $random_17_digits = mt_rand(1e16, 1e17 - 1);
        $random_two_digits = str_pad(mt_rand(0, 99), 2, '0', STR_PAD_LEFT);

        $random_19_digits = $random_17_digits . $random_two_digits;

        // If you need to use it as an integer, you can cast it to an integer
        return (int)$random_19_digits;
    }
}
