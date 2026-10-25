<?php

namespace App\Http\Resources\Api\V1;

use App\Models\EventOccurrence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One date of an event.
 *
 * @mixin EventOccurrence
 */
class OccurrenceResource extends JsonResource
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
            'startsAt' => $this->starts_at->utc()->toIso8601String(),
            'endsAt' => $this->ends_at->utc()->toIso8601String(),
            /** When this date, or the whole event, was cancelled. */
            'cancelledAt' => ($this->cancelled_at ?? $this->event->cancelled_at)?->utc()->toIso8601String(),
            'rosterPublished' => $this->isRosterPublished(),
        ];
    }
}
