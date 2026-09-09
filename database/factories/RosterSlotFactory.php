<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\RosterShift;
use App\Models\RosterSlot;
use Carbon\CarbonImmutable;
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
            'start_day_offset' => 0,
            'start_time' => fn (array $attributes): string => substr($this->event($attributes['shift_id'])->local_start, 11),
            'end_day_offset' => function (array $attributes): int {
                $event = $this->event($attributes['shift_id']);

                return (int) CarbonImmutable::parse(substr($event->local_start, 0, 10), 'UTC')
                    ->diffInDays(CarbonImmutable::parse(substr($event->local_end, 0, 10), 'UTC'));
            },
            'end_time' => fn (array $attributes): string => substr($this->event($attributes['shift_id'])->local_end, 11),
        ];
    }

    private function event(int $shiftId): Event
    {
        return RosterShift::findOrFail($shiftId)->roster->event;
    }
}
