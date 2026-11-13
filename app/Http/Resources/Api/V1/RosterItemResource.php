<?php

namespace App\Http\Resources\Api\V1;

use App\Staffing\RosterItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A position worked for a span of one date: a shift, booked or open, or a Roster Entry.
 *
 * @property RosterItem $resource
 */
class RosterItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** `shift` on an event staffed through shifts, `rosterEntry` on one staffed through a roster. */
            'kind' => $this->resource->isShift() ? 'shift' : 'rosterEntry',
            /**
             * The shift's or the Roster Entry's id, depending on `kind`.
             *
             * @var int
             */
            'id' => $this->resource->source->id,
            'position' => [
                'id' => $this->resource->position->id,
                'callsign' => $this->resource->position->callsign,
                'frequency' => $this->resource->position->frequency,
            ],
            'startsAt' => $this->resource->startsAt->utc()->toIso8601String(),
            'endsAt' => $this->resource->endsAt->utc()->toIso8601String(),
            /** Who works it, or null while a shift is open. */
            'controller' => $this->resource->controller() !== null ? new ControllerResource($this->resource->controller()) : null,
        ];
    }
}
