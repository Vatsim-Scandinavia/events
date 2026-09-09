<?php

namespace Database\Factories;

use App\Actions\EventSchedule;
use App\Models\EventRoster;
use App\Models\RosterInterest;
use App\Models\RosterPosition;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RosterInterest> */
class RosterInterestFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'roster_id' => EventRoster::factory()->openInterest(),
            'user_cid' => User::factory(),
            'occurrence_date' => fn (array $attributes): string => substr(EventRoster::whereKey($attributes['roster_id'])->firstOrFail()->event->local_start, 0, 10),
            'position_ids' => fn (array $attributes): array => [RosterPosition::factory()->create(['roster_id' => $attributes['roster_id']])->id],
            'position_callsigns' => fn (array $attributes): array => RosterPosition::whereIn('id', $attributes['position_ids'])->orderBy('id')->pluck('callsign')->all(),
            'occurrence_ends_at' => fn (array $attributes): string => $this->occurrence($attributes)['ends_at'],
            'availability' => function (array $attributes): array {
                $occurrence = $this->occurrence($attributes);

                return [[
                    'starts_at' => CarbonImmutable::parse($occurrence['starts_at'])->utc()->format('Y-m-d\TH:i'),
                    'ends_at' => CarbonImmutable::parse($occurrence['ends_at'])->utc()->format('Y-m-d\TH:i'),
                ]];
            },
        ];
    }

    /** @param array<string, mixed> $attributes
     * @return array{date: string, starts_at: string|null, ends_at: string|null, status: string, reason: string|null}
     */
    private function occurrence(array $attributes): array
    {
        $roster = EventRoster::whereKey($attributes['roster_id'])->firstOrFail();

        return app(EventSchedule::class)->occurrence($roster->event, $attributes['occurrence_date']);
    }
}
