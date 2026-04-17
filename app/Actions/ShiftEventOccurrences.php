<?php

namespace App\Actions;

use App\Models\Event;
use App\Models\EventOccurrence;
use Carbon\Carbon;

final class ShiftEventOccurrences
{
    /**
     * Shift all future scheduled occurrences of a recurring event by the same
     * offset as the representative occurrence was moved, keeping time-of-day
     * consistent across the series.
     */
    public function __invoke(Event $event, EventOccurrence $anchor, int $shiftSeconds, int $durationSeconds): void
    {
        $event->occurrences()
            ->where('id', '!=', $anchor->id)
            ->where('start_time', '>=', now())
            ->get()
            ->each(function (EventOccurrence $occ) use ($shiftSeconds, $durationSeconds) {
                $newStart = Carbon::parse($occ->start_time, 'UTC')->addSeconds($shiftSeconds);
                $occ->update([
                    'start_time' => $newStart,
                    'end_time'   => $newStart->copy()->addSeconds($durationSeconds),
                ]);
            });
    }
}
