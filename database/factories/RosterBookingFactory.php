<?php

namespace Database\Factories;

use App\Actions\RosterSchedule;
use App\Models\RosterBooking;
use App\Models\RosterSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RosterBooking> */
class RosterBookingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'slot_id' => RosterSlot::factory(),
            'roster_id' => fn (array $attributes): int => RosterSlot::whereKey($attributes['slot_id'])->firstOrFail()->shift->roster_id,
            'user_cid' => User::factory(),
            'occurrence_date' => fn (array $attributes): string => substr(RosterSlot::whereKey($attributes['slot_id'])->firstOrFail()->shift->roster->event->local_start, 0, 10),
            'callsign' => fn (array $attributes): string => RosterSlot::whereKey($attributes['slot_id'])->firstOrFail()->callsign,
            'shift_name' => fn (array $attributes): string => RosterSlot::whereKey($attributes['slot_id'])->firstOrFail()->shift->name,
            'starts_at' => fn (array $attributes): string => $this->time($attributes, 'starts_at'),
            'ends_at' => fn (array $attributes): string => $this->time($attributes, 'ends_at'),
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function time(array $attributes, string $field): string
    {
        $slot = RosterSlot::whereKey($attributes['slot_id'])->firstOrFail();

        return app(RosterSchedule::class)->timesFor($slot, $attributes['occurrence_date'])[$field]->toIso8601String();
    }
}
