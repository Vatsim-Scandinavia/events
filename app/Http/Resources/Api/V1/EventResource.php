<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Event;
use App\Models\EventType;
use App\Services\EventBanners;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An event as the public API lists it. Times are UTC and values are raw, so unlike the
 * web app's resources nothing here depends on who is asking.
 *
 * @mixin Event
 */
class EventResource extends JsonResource
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
            'name' => $this->name,
            /** The event's page in the Hub. */
            'url' => route('events.show', $this->resource),
            'type' => ['name' => $this->eventType?->name ?? EventType::FALLBACK],
            'fir' => ['icao' => $this->fir->icao, 'name' => $this->fir->name],
            /** @var list<string> */
            'airports' => $this->airports ?? [],
            /** @var list<array{from: string, to: string, route: string}> */
            'routes' => $this->routes ?? [],
            'banner' => app(EventBanners::class)->url($this->banner_path),
            /** Where pilots reserve arrival or departure slots. */
            'slotUrl' => $this->booking_url,
            'briefingUrl' => $this->briefing_url,
            /** The time zone the event's schedule repeats in. */
            'timezone' => $this->timezone,
            'repeats' => $this->repeats,
            'staffingMode' => $this->staffing_mode,
            'publishedAt' => $this->published_at->utc()->toIso8601String(),
            'cancelledAt' => $this->cancelled_at?->utc()->toIso8601String(),
            /** Every date of the event. Left out where the event is embedded in one of its dates. */
            'occurrences' => OccurrenceResource::collection($this->whenLoaded('occurrences')),
        ];
    }
}
