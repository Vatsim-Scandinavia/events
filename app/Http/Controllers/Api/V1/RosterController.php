<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StaffingMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RosterItemResource;
use App\Models\EventOccurrence;
use App\Models\EventPosition;
use App\Models\PositionShift;
use App\Models\RosterEntry;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;

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

        $items = match ($event->staffing_mode) {
            StaffingMode::Shifts => $this->shifts($occurrence),
            StaffingMode::OpenInterest => $occurrence->isRosterPublished()
                ? $occurrence->rosterEntries()->with(['position', 'user'])->get()->map(fn (RosterEntry $entry) => RosterItemResource::forRosterEntry($entry))
                : collect(),
            default => collect(),
        };

        return RosterItemResource::collection($items->sortBy([
            fn (RosterItemResource $a, RosterItemResource $b) => $a->startsAt <=> $b->startsAt,
            fn (RosterItemResource $a, RosterItemResource $b) => strcmp($a->position->callsign, $b->position->callsign),
            fn (RosterItemResource $a, RosterItemResource $b) => $a->resource->id <=> $b->resource->id,
        ])->values());
    }

    /**
     * @return Collection<int, RosterItemResource>
     */
    private function shifts(EventOccurrence $occurrence): Collection
    {
        $bookings = $occurrence->bookings()->with('user')->get()->keyBy('position_shift_id');

        return $occurrence->event->positions()->with('shifts')->get()
            ->flatMap(fn (EventPosition $position) => $position->shifts->map(
                fn (PositionShift $shift) => RosterItemResource::forShift($shift, $position, $occurrence, $bookings->get($shift->id)),
            ));
    }
}
