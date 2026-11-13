<?php

namespace App\Staffing;

use App\Enums\RosterState;
use App\Enums\StaffingMode;
use App\Models\EventOccurrence;
use App\Models\EventPosition;
use App\Models\PositionShift;
use App\Models\RosterEntry;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who works which position, and when, on an Occurrence, as someone may see it. Every place
 * that shows a Roster (the Event page, the public API, Discord, Control Center) reads it
 * from here, so none of them can show more than the others:
 *
 * - A Draft's Roster shows only to those who can see the Draft.
 * - On an Event staffed through shifts, every shift shows, booked or open.
 * - On one staffed through Staffing Interest, the Roster Entries show once the roster is
 *   published, and before that to staff who can see staffing (see EventPolicy).
 *
 * Items come in the order staff set for positions, then by start, each with its Section.
 */
final class Roster
{
    /**
     * @param  Collection<int, RosterItem>  $items
     */
    private function __construct(
        private RosterState $state,
        private Collection $items,
    ) {}

    /**
     * The Roster of the date, for a viewer, or for the public when there's none.
     */
    public static function of(EventOccurrence $occurrence, ?User $viewer = null): self
    {
        $event = $occurrence->event;

        $state = match (true) {
            $event->isDraft() && ! $viewer?->can('view', $event) => RosterState::Draft,
            $event->staffing_mode === StaffingMode::None => RosterState::NotStaffed,
            $event->staffing_mode === StaffingMode::OpenInterest
                && ! $occurrence->isRosterPublished()
                && ! $viewer?->can('viewStaffing', $event) => RosterState::Unpublished,
            default => RosterState::Shown,
        };

        if ($state !== RosterState::Shown) {
            return new self($state, collect());
        }

        return new self($state, $event->staffing_mode === StaffingMode::Shifts ? self::shifts($occurrence) : self::entries($occurrence));
    }

    public function state(): RosterState
    {
        return $this->state;
    }

    /**
     * What's on the Roster, empty unless it's shown.
     *
     * @return Collection<int, RosterItem>
     */
    public function items(): Collection
    {
        return $this->items;
    }

    /**
     * Every shift on the date, booked or open: by position, then by start, as the positions
     * and their shifts are ordered.
     *
     * @return Collection<int, RosterItem>
     */
    private static function shifts(EventOccurrence $occurrence): Collection
    {
        $event = $occurrence->event;
        $positions = $event->positions()->with('shifts')->get();
        $sections = $event->sectionsFor($positions);
        $bookings = $occurrence->bookings()->with('user')->get()->keyBy('position_shift_id');

        return $positions->flatMap(fn (EventPosition $position) => $position->shifts->map(function (PositionShift $shift) use ($occurrence, $position, $sections, $bookings) {
            $booking = $bookings->get($shift->id);

            return new RosterItem($shift, $position, $sections[$position->id], $shift->startsAtFor($occurrence), $shift->endsAtFor($occurrence), $booking);
        }))->values();
    }

    /**
     * The date's Roster Entries.
     *
     * @return Collection<int, RosterItem>
     */
    private static function entries(EventOccurrence $occurrence): Collection
    {
        $event = $occurrence->event;
        $positions = $event->positions()->get();
        $sections = $event->sectionsFor($positions);
        $order = $positions->pluck('id')->flip();
        $positions = $positions->keyBy('id');

        return $occurrence->rosterEntries()->with('user')->get()
            ->sortBy([
                fn (RosterEntry $a, RosterEntry $b) => $order[$a->event_position_id] <=> $order[$b->event_position_id],
                fn (RosterEntry $a, RosterEntry $b) => $a->starts_at <=> $b->starts_at,
            ])
            ->map(fn (RosterEntry $entry) => new RosterItem($entry, $positions[$entry->event_position_id], $sections[$entry->event_position_id], $entry->starts_at, $entry->ends_at))
            ->values();
    }
}
