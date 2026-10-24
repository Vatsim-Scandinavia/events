<?php

namespace App\Http\Resources\Api\V1;

use App\Models\EventOccurrence;
use Illuminate\Http\Request;

/**
 * A date on its own, with how it's staffed and a summary of its event.
 *
 * @mixin EventOccurrence
 */
class OccurrenceDetailResource extends OccurrenceResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            /** Whether controllers staff this date through shifts or a roster. */
            'staffingMode' => $this->event->staffing_mode,
            'event' => new EventResource($this->event),
        ];
    }
}
