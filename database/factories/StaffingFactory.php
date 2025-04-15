<?php

namespace Database\Factories;

use App\Models\Event;
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
        return [
            'description' => $this->faker->paragraph(),
            'channel_id' => $this->ranNumbers(),
            'message_id' => $this->ranNumbers(),
            'section_1_title' => $this->faker->sentence(1),
            'section_2_title' => $this->faker->sentence(1),
            'section_3_title' => $this->faker->sentence(1),
            'section_4_title' => $this->faker->sentence(1),
            'event_id' => Event::factory()->create()->id,
        ];
    }

    protected function ranNumbers()
    {
        $random_17_digits = mt_rand(1e16, 1e17 - 1);
        $random_two_digits = str_pad(mt_rand(0, 99), 2, '0', STR_PAD_LEFT);

        $random_19_digits = $random_17_digits.$random_two_digits;

        // If you need to use it as an integer, you can cast it to an integer
        return (int) $random_19_digits;
    }
}
