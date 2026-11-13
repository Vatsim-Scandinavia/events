<?php

namespace App\Enums;

/**
 * Why someone can't show Staffing Interest in a date right now. Reasons about the date come
 * before reasons about the person (see StaffingInterests::refusal).
 */
enum InterestRefusal: string
{
    /** The Event isn't published yet. */
    case Draft = 'draft';

    /** The Event isn't staffed through Staffing Interest. */
    case NotInterest = 'not_interest';

    /** The date, or the whole Event, has been cancelled. */
    case Cancelled = 'cancelled';

    /** The window for showing interest hasn't opened yet. */
    case NotOpenYet = 'not_open_yet';

    /** The window for showing interest has closed. */
    case Closed = 'closed';

    /** Controllers only staff the Event's next date for now. */
    case NotNextDate = 'not_next_date';

    /** Nobody's signed in. */
    case SignInNeeded = 'sign_in_needed';

    /** The person may not control on this Event: no role lets them, across the Hub or in its FIR. */
    case NotController = 'not_controller';
}
