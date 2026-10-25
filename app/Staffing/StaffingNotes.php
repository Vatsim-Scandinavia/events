<?php

namespace App\Staffing;

use App\Models\EventOccurrence;
use App\Models\EventPosition;
use App\Models\User;

/**
 * Something other than the core that has a word to say about staffing, shown next to it but
 * never enforced: an Integration feature warning that a controller isn't endorsed for a
 * position, or that a booking couldn't be sent on. See ADR 0004.
 */
interface StaffingNotes
{
    /**
     * Notes for Event Staff on the date's Shift Bookings and Roster Entries, keyed
     * "shift_booking:{id}" or "roster_entry:{id}". The controller concerned sees them too.
     *
     * @return array<string, list<string>>
     */
    public function staffingNotes(EventOccurrence $occurrence): array;

    /**
     * What the controller should know before working the position, such as a missing
     * endorsement. Null when there's nothing to say, or it can't tell.
     */
    public function positionNote(User $controller, EventPosition $position): ?string;
}
