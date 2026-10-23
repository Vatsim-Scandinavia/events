<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Models\EventType;
use App\Services\DisplayTime;
use App\Services\EventBanners;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Event
 */
class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Dates are formatted in the viewer's time zone, UTC (zulu) unless they picked another.
     * For an event that repeats they describe its next date.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $next = $this->currentOccurrence() ?? $this->resource;
        $display = app(DisplayTime::class);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->eventType?->name ?? EventType::FALLBACK,
            'airports' => $this->airports,
            'fir' => $this->fir ? ['icao' => $this->fir->icao, 'name' => $this->fir->name] : null,
            'startsAt' => $next->starts_at->utc()->toIso8601String(),
            'day' => (int) $display->date($next->starts_at, 'j'),
            'weekday' => $display->date($next->starts_at, 'D'),
            'date' => $display->date($next->starts_at),
            'month' => $display->date($next->starts_at, 'F Y'),
            'time' => $display->range($next->starts_at, $next->ends_at),
            'banner' => app(EventBanners::class)->url($this->banner_path),
            'repeats' => $this->repeats->describe($this->starts_at->setTimezone($this->timezone)),
            'draft' => $this->isDraft(),
        ];
    }
}
