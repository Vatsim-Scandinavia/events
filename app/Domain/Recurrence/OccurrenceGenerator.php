<?php

namespace App\Domain\Recurrence;

use Carbon\Carbon;

class OccurrenceGenerator
{
    public function generate(
        RecurrenceRule $rule,
        Carbon $from,
        Carbon $to,
        int $durationSeconds,
    ): array {
        $starts = $rule->occurrencesBetween($from, $to);
        
        $occurrences = [];

        foreach ($starts as $start) {
            $start = $start instanceof Carbon ? $start : Carbon::instance($start);

            $occurrences[] = [
                'start_time' => $start->copy()->utc()->toDateTimeString(),
                'end_time'   => $start->copy()->addSeconds($durationSeconds)->utc()->toDateTimeString(),
                'status'     => 'scheduled',
            ];
        }

        return $occurrences;
    }
}