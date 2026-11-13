<?php

namespace App\Enums;

/**
 * Whether an Occurrence's Roster can be shown to someone, and if not, why not.
 */
enum RosterState
{
    /** The Event is a Draft, and this viewer can't see it. */
    case Draft;

    /** The Event isn't staffed through the Hub. */
    case NotStaffed;

    /** The roster from Staffing Interest isn't published yet, and this viewer isn't staff. */
    case Unpublished;

    /** The Roster can be shown to this viewer, though it may have nothing on it yet. */
    case Shown;
}
