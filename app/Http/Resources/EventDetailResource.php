<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Services\DisplayTime;
use App\Services\EventDescription;
use Illuminate\Http\Request;

/**
 * The full event, for its details page.
 *
 * @mixin Event
 */
class EventDetailResource extends EventResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $next = $this->currentOccurrence() ?? $this->resource;
        $startsAt = $next->starts_at->utc();
        $endsAt = $next->ends_at->utc();

        return [
            ...parent::toArray($request),
            'description' => EventDescription::toHtml($this->description),
            'endsAt' => $endsAt->toIso8601String(),
            // Pilots file and brief in zulu, so it stays on hand whatever zone they picked.
            'zuluTime' => (new DisplayTime)->range($startsAt, $endsAt),
            'cancelled' => $this->isCancelled(),
            'duration' => DisplayTime::duration((int) $startsAt->diffInMinutes($endsAt)),
            'hasEnded' => $endsAt->isPast(),
            'routes' => $this->routes ?? [],
            'bookingUrl' => $this->booking_url,
            'briefingUrl' => $this->briefing_url,
        ];
    }
}
