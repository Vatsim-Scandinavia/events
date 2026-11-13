<?php

namespace App\Enums;

/**
 * Why someone can't book a shift on a date right now. Reasons about the date come before
 * reasons about the person (see ShiftBookings::refusal).
 */
enum BookingRefusal: string
{
    /** The Event isn't published yet. */
    case Draft = 'draft';

    /** The Event isn't staffed through shifts. */
    case NotShifts = 'not_shifts';

    /** Booking closed when the date started. */
    case Started = 'started';

    /** The date, or the whole Event, has been cancelled. */
    case Cancelled = 'cancelled';

    /** Controllers only book the Event's next date for now. */
    case NotNextDate = 'not_next_date';

    /** Nobody's signed in. */
    case SignInNeeded = 'sign_in_needed';

    /** The person may not control on this Event: no role lets them, across the Hub or in its FIR. */
    case NotController = 'not_controller';
}
