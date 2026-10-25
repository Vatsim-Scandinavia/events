<?php

namespace App\Http\Resources\Api\V1;

use App\Models\RosterEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A controller placed on a position for a span of a date.
 *
 * @mixin RosterEntry
 */
class RosterEntryResource extends JsonResource
{
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
            'startsAt' => $this->starts_at->utc()->toIso8601String(),
            'endsAt' => $this->ends_at->utc()->toIso8601String(),
            'controller' => new ControllerResource($this->user),
        ];
    }
}
