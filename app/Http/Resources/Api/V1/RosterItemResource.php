<?php

namespace App\Http\Resources\Api\V1;

use App\Models\EventOccurrence;
use App\Models\EventPosition;
use App\Models\PositionShift;
use App\Models\RosterEntry;
use App\Models\ShiftBooking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A position worked for a span of one date: a shift, booked or open, or a Roster Entry.
 *
 * @property PositionShift|RosterEntry $resource
 */
class RosterItemResource extends JsonResource
{
    public function __construct(
        PositionShift|RosterEntry $item,
        public readonly EventPosition $position,
        public readonly CarbonImmutable $startsAt,
        public readonly CarbonImmutable $endsAt,
        public readonly ?User $controller,
    ) {
        parent::__construct($item);
    }

    /**
     * A shift as it falls on one date, with who booked it there.
     */
    public static function forShift(PositionShift $shift, EventPosition $position, EventOccurrence $occurrence, ?ShiftBooking $booking): self
    {
        return new self($shift, $position, $shift->startsAtFor($occurrence), $shift->endsAtFor($occurrence), $booking?->user);
    }

    public static function forRosterEntry(RosterEntry $entry): self
    {
        return new self($entry, $entry->position, $entry->starts_at, $entry->ends_at, $entry->user);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** `shift` on an event staffed through shifts, `rosterEntry` on one staffed through a roster. */
            'kind' => $this->resource instanceof PositionShift ? 'shift' : 'rosterEntry',
            /**
             * The shift's or the Roster Entry's id, depending on `kind`.
             *
             * @var int
             */
            'id' => $this->resource->id,
            'position' => [
                'id' => $this->position->id,
                'callsign' => $this->position->callsign,
                'frequency' => $this->position->frequency,
            ],
            'startsAt' => $this->startsAt->utc()->toIso8601String(),
            'endsAt' => $this->endsAt->utc()->toIso8601String(),
            /** Who works it, or null while a shift is open. */
            'controller' => $this->controller !== null ? new ControllerResource($this->controller) : null,
        ];
    }
}
