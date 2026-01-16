<?php

namespace Database\Factories;

use App\Models\Calendar;
use App\Models\User;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{

    public function configure()
    {
        return $this->afterCreating(function (Event $event) {
            // Automatically create a default instance if one doesn't exist
            // Check both count and if an instance with the default times already exists
            $defaultStart = now()->addDay();
            $defaultEnd = $defaultStart->copy()->addHours(2);
            
            $hasDefaultInstance = $event->instances()
                ->where('start_time', $defaultStart->toDateTimeString())
                ->where('end_time', $defaultEnd->toDateTimeString())
                ->exists();
            
            if ($event->instances()->count() === 0 && !$hasDefaultInstance) {
                $event->instances()->create([
                    'start_time' => $defaultStart,
                    'end_time' => $defaultEnd,
                ]);
            }
        });
    }
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // This checks if a calendar exists first
            'calendar_id' => Calendar::query()->inRandomOrder()->first()?->id ?? Calendar::factory(), 
            
            // Same logic for users to avoid creating 100s of users
            'user_id' => User::query()->inRandomOrder()->first()?->id ?? User::factory(),
            
            'title' => $this->faker->sentence(3),
            'short_description' => $this->faker->text(280),
            'long_description' => $this->faker->paragraph(),
            'image' => null,
        ];
    }
}