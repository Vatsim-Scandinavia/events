<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StaffingMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RosterEntryResource;
use App\Models\EventOccurrence;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RosterController extends Controller
{
    /**
     * List a date's published roster.
     *
     * Entries are ordered by start. The list stays empty until staff publish the roster, so
     * poll until the date's `rosterPublished` is true. Events not staffed through a roster
     * have none.
     */
    public function index(EventOccurrence $occurrence): AnonymousResourceCollection
    {
        $event = $occurrence->event;

        // A Draft's dates are not found, rather than forbidden, so the Draft isn't revealed.
        abort_if($event->isDraft(), 404);

        if ($event->staffing_mode !== StaffingMode::OpenInterest || ! $occurrence->isRosterPublished()) {
            return RosterEntryResource::collection([]);
        }

        return RosterEntryResource::collection($occurrence->rosterEntries()->with(['position', 'user'])->get());
    }
}
