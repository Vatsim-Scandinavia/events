<?php

namespace App\Staffing;

use App\Models\EventPosition;
use App\Models\EventSection;
use App\Models\PositionShift;
use App\Models\RosterEntry;
use App\Models\ShiftBooking;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * A position worked for a span of one date: a shift, booked or open, on an Event staffed
 * through shifts, or a Roster Entry on one staffed through Staffing Interest.
 */
final readonly class RosterItem
{
    /**
     * @param  ?EventSection  $section  The Section the position falls under.
     * @param  ?User  $controller  Who works it, or null while a shift is open.
     * @param  ?ShiftBooking  $booking  The shift's booking on this date, if it's booked.
     */
    public function __construct(
        public PositionShift|RosterEntry $source,
        public EventPosition $position,
        public ?EventSection $section,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public ?User $controller,
        public ?ShiftBooking $booking = null,
    ) {}

    public function isShift(): bool
    {
        return $this->source instanceof PositionShift;
    }
}
