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
            'position_ids' => fn (array $attributes): array => [RosterPosition::factory()->create(['roster_id' => $attributes['roster_id']])->id],
            'availability' => function (array $attributes): array {
                $roster = EventRoster::whereKey($attributes['roster_id'])->firstOrFail();
                $occurrence = app(EventSchedule::class)->occurrence($roster->event, $roster->occurrence_date);

                return [[
                    'starts_at' => CarbonImmutable::parse($occurrence['starts_at'])->utc()->format('Y-m-d\TH:i'),
                    'ends_at' => CarbonImmutable::parse($occurrence['ends_at'])->utc()->format('Y-m-d\TH:i'),
                ]];
            },
        ];
    }
}
