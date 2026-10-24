<?php

namespace Database\Factories;

use App\Enums\IntegrationRunStatus;
use App\Models\IntegrationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntegrationRun>
 */
class IntegrationRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'integration' => 'example',
            'feature' => 'roster',
            'status' => IntegrationRunStatus::Succeeded,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ];
    }

    /**
     * A run whose changes were held back for an Administrator to approve.
     */
    public function held(string $reason = 'It would remove 30 of 40 Controller roles.'): static
    {
        return $this->state(['status' => IntegrationRunStatus::Held, 'message' => $reason]);
    }
}
