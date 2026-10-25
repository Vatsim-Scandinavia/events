<?php

namespace App\Http\Resources\Api\V1;

use App\Models\EventOccurrence;
use App\Models\PositionShift;
use App\Models\ShiftBooking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A shift as it falls on one date, with who booked it there.
 *
 * @mixin PositionShift
 */
class ShiftResource extends JsonResource
{
    public function __construct(PositionShift $shift, private EventOccurrence $occurrence, private ?ShiftBooking $booking = null)
    {
        parent::__construct($shift);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'position' => [
                'id' => $this->position->id,
                'callsign' => $this->position->callsign,
                'frequency' => $this->position->frequency,
            ],
            'startsAt' => $this->startsAtFor($this->occurrence)->utc()->toIso8601String(),
            'endsAt' => $this->endsAtFor($this->occurrence)->utc()->toIso8601String(),
            /** Who booked the shift on this date, or null while it's open. */
            'controller' => $this->booking !== null ? new ControllerResource($this->booking->user) : null,
        ];
    }
}
