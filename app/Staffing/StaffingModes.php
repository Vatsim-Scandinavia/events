<?php

namespace App\Staffing;

use App\Enums\StaffingMode;
use App\Models\Event;
use App\Models\PositionShift;
use App\Models\RosterEntry;
use App\Models\StaffingInterest;
use Illuminate\Support\Facades\DB;

/**
 * Switching how an event is staffed. Data that belongs to the old mode is cleared, so it
 * can't resurface if staff switch back later:
 *
 * - Shifts and their bookings.
 * - Interest and rosters for dates that haven't ended (rosters of past dates stay as a record).
 * - Positions too, when staffing is switched off.
 */
final class StaffingModes
{
    public function switch(Event $event, StaffingMode $mode): void
    {
        if ($event->staffing_mode === $mode) {
            return;
        }

        DB::transaction(function () use ($event, $mode) {
            PositionShift::query()->whereIn('event_position_id', $event->positions()->select('id'))->delete();

            $upcoming = $event->occurrences()->where('ends_at', '>', now())->pluck('id');
            StaffingInterest::query()->whereIn('event_occurrence_id', $upcoming)->delete();
            RosterEntry::query()->whereIn('event_occurrence_id', $upcoming)->delete();
            $event->occurrences()->whereKey($upcoming)->update(['roster_published_at' => null]);

            if ($mode === StaffingMode::None) {
                $event->positions()->delete();
            }

            $event->update(['staffing_mode' => $mode]);
        });
    }
}
