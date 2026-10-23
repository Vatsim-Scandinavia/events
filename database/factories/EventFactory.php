<?php

namespace Database\Factories;

use App\Enums\StaffingMode;
use App\Models\Event;
use App\Models\Fir;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addDays(fake()->numberBetween(1, 60))->setTime(fake()->numberBetween(10, 19), 0);

        return [
            'fir_id' => Fir::factory(),
            'name' => fake()->words(3, true),
            'airports' => fake()->randomElements(['EKCH', 'EKBI', 'EKYT', 'EKAH', 'ESSA', 'ENGM', 'EFHK'], fake()->numberBetween(1, 3)),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(fake()->numberBetween(2, 5)),
            'banner_path' => null,
            'description' => fake()->paragraphs(2, true),
            'routes' => null,
            'booking_url' => null,
            'briefing_url' => null,
        ];
    }

    /**
     * An event controllers staff by booking pre-defined shifts.
     */
    public function withShifts(): static
    {
        return $this->state(['staffing_mode' => StaffingMode::Shifts]);
    }

    /**
     * An event controllers staff by showing interest, with staff laying a roster.
     */
    public function withOpenInterest(): static
    {
        return $this->state(['staffing_mode' => StaffingMode::OpenInterest]);
    }

    /**
     * An event that has already finished.
     */
    public function past(): static
    {
        return $this->state(function () {
            $startsAt = now()->subDays(fake()->numberBetween(2, 30));

            return [
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addHours(3),
            ];
        });
    }
}
