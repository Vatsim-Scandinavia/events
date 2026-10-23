<?php

namespace App\Enums;

/**
 * How controllers staff an event: by booking shifts, or by showing interest for a roster.
 */
enum StaffingMode: string
{
    /** Staffing isn't managed through the hub. */
    case None = 'none';

    /** Controllers show interest and availability; staff lay and publish a roster. */
    case OpenInterest = 'open_interest';

    /** Staff define shifts per position; controllers book them first come, first served. */
    case Shifts = 'shifts';
}
