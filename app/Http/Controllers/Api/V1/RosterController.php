<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RosterItemResource;
use App\Models\EventOccurrence;
use App\Staffing\Roster;
use App\Staffing\RosterItem;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RosterController extends Controller
{
    /**
     * List who works which position, and when, on a date.
     *
     * On an event staffed through shifts, every shift is listed, booked or open, at any time;
     * an open shift has no `controller`. On an event staffed through a roster, the list stays
     * empty until staff publish it, so poll until the date's `rosterPublished` is true. Events
     * not staffed through the hub have none. Items are ordered by start, then callsign.
     */
    public function index(EventOccurrence $occurrence): AnonymousResourceCollection
    {
        $event = $occurrence->event;

        // A Draft's dates are not found, rather than forbidden, so the Draft isn't revealed.
        abort_if($event->isDraft(), 404);

        return RosterItemResource::collection(Roster::of($occurrence)->items()->sortBy([
            fn (RosterItem $a, RosterItem $b) => $a->startsAt <=> $b->startsAt,
            fn (RosterItem $a, RosterItem $b) => strcmp($a->position->callsign, $b->position->callsign),
            fn (RosterItem $a, RosterItem $b) => $a->source->id <=> $b->source->id,
        ])->values());
    }
}
