<?php

namespace Database\Factories;

use App\Models\EventInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

class StaffingFactory extends Factory
{
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
            'event_instance_id' => EventInstance::factory(), 
        ];
    }

    protected function ranNumbers()
    {
        return (int) (mt_rand(1e16, 1e17 - 1) . str_pad(mt_rand(0, 99), 2, '0', STR_PAD_LEFT));
    }
}