<?php

namespace Database\Factories;

use App\Enums\ControllerGrantOrigin;
use App\Models\ControllerGrant;
use App\Models\Fir;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ControllerGrant>
 */
class ControllerGrantFactory extends Factory
{
    /**
     * A grant given by hand, Hub-wide.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'fir_id' => null,
            'origin' => ControllerGrantOrigin::Hand,
        ];
    }

    /**
     * Only in the given FIR.
     */
    public function in(Fir $fir): static
    {
        return $this->state(['fir_id' => $fir->id]);
    }

    /**
     * Given by the Controller Source in charge.
     */
    public function fromSource(): static
    {
        return $this->state(['origin' => ControllerGrantOrigin::Source]);
    }
}
