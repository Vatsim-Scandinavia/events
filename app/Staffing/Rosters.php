<?php

namespace App\Staffing;

use App\Enums\StaffingMode;
use App\Models\EventOccurrence;
use Illuminate\Validation\ValidationException;

/**
 * Publishing the roster for an occurrence. Roster slots themselves are saved with the
 * rest of the staffing plan (see StaffingPlan).
 */
final class Rosters
{
    /**
     * @throws ValidationException
     */
    public function publish(EventOccurrence $occurrence): void
    {
        $this->ensureEditable($occurrence);

        // Staff can lay the roster on a Draft, but controllers would be told about an event they can't see.
        if ($occurrence->event->isDraft()) {
            throw ValidationException::withMessages(['roster' => 'Publish the event before its roster.']);
        }

        $occurrence->update(['roster_published_at' => $occurrence->roster_published_at ?? now()]);
    }

    /**
     * @throws ValidationException
     */
    public function unpublish(EventOccurrence $occurrence): void
    {
        $this->ensureEditable($occurrence);

        $occurrence->update(['roster_published_at' => null]);
    }

    /**
     * @throws ValidationException when the event has no roster or it has been archived
     */
    public function ensureEditable(EventOccurrence $occurrence): void
    {
        if ($occurrence->event->staffing_mode !== StaffingMode::OpenInterest) {
            throw ValidationException::withMessages(['roster' => 'Only open-interest events have a roster.']);
        }

        if ($occurrence->isRosterArchived()) {
            throw ValidationException::withMessages(['roster' => 'The event has ended, so its roster is archived.']);
        }
    }
}
