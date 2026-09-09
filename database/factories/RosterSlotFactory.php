<?php

namespace Database\Factories;

use App\Actions\EventSchedule;
use App\Models\RosterShift;
use App\Models\RosterSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RosterSlot> */
class RosterSlotFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'shift_id' => RosterShift::factory(),
            'callsign' => fake()->unique()->regexify('[A-Z]{4}').'_TWR',
            'starts_at' => fn (array $attributes): string => $this->occurrence($attributes['shift_id'])['starts_at'],
            'ends_at' => fn (array $attributes): string => $this->occurrence($attributes['shift_id'])['ends_at'],
            'booked_by' => null,
        ];
    }

    /** @return array{date: string, starts_at: string|null, ends_at: string|null, status: string, reason: string|null} */
    private function occurrence(int $shiftId): array
    {
        $roster = RosterShift::findOrFail($shiftId)->roster;

        return app(EventSchedule::class)->occurrence($roster->event, $roster->occurrence_date);
    }
}
