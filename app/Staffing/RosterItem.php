<?php

namespace App\Staffing;

use App\Models\EventPosition;
use App\Models\EventSection;
use App\Models\PositionShift;
use App\Models\RosterEntry;
use App\Models\ShiftBooking;
use App\Models\User;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * One line of a Roster: a position worked for a span of one date. On an Event staffed through
 * shifts it's a shift, booked or open; on one staffed through Staffing Interest it's a Roster
 * Entry. So a Roster Entry is one kind of roster item, not another name for it.
 */
final readonly class RosterItem
{
    /**
     * @param  ?EventSection  $section  The Section the position falls under.
     * @param  ?ShiftBooking  $booking  The shift's booking on this date, if it's booked.
     */
    public function __construct(
        public PositionShift|RosterEntry $source,
        public EventPosition $position,
        public ?EventSection $section,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public ?ShiftBooking $booking = null,
    ) {}

    public function isShift(): bool
    {
        return $this->source instanceof PositionShift;
    }

    /**
     * Who works it, or null while a shift is open.
     */
    public function controller(): ?User
    {
        return $this->source instanceof RosterEntry ? $this->source->user : $this->booking?->user;
    }

    /**
     * The shift, on an Event staffed through shifts.
     */
    public function shift(): PositionShift
    {
        return $this->source instanceof PositionShift ? $this->source : throw new LogicException('This roster item is a Roster Entry, not a shift.');
    }

    /**
     * The Roster Entry, on an Event staffed through Staffing Interest.
     */
    public function rosterEntry(): RosterEntry
    {
        return $this->source instanceof RosterEntry ? $this->source : throw new LogicException('This roster item is a shift, not a Roster Entry.');
    }
}
