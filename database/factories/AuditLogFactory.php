<?php

namespace Database\Factories;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_cid' => null,
            'actor_name' => null,
            'subject_type' => 'fir',
            'subject_id' => fake()->numberBetween(1, 100000),
            'subject_label' => 'EKDK — Copenhagen FIR',
            'event' => 'updated',
            'source' => 'manual',
            'old_values' => ['name' => 'Old name'],
            'new_values' => ['name' => 'Copenhagen FIR'],
            'created_at' => now(),
        ];
    }
}
