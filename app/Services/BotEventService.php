<?php

namespace App\Services;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BotEventService
{
    /**
     * Return all events, optionally filtered.
     */
    public function getAll(bool $upcoming = true, bool $staffingOnly = false): Collection
    {
        $query = Event::with([
            'calendar',
            'staffing.sections.positions',
            'occurrences' => fn($q) => $q
                ->where('start_time', '>=', now())
                ->where('status', '!=', 'cancelled')
                ->orderBy('start_time'),
        ]);

        if ($upcoming) {
            $query->whereHas('occurrences', fn($q) => $q->where('end_time', '>=', now()));
        }

        if ($staffingOnly) {
            $query->whereHas('staffing');
        }

        return $query->orderByRaw(
            '(SELECT MIN(o.start_time) FROM event_occurrences o WHERE o.event_id = events.id AND o.end_time >= ?)',
            [now()]
        )->get();
    }

    /**
     * Return a single event by primary key.
     */
    public function getById(int $id): Event
    {
        return Event::with([
            'calendar',
            'staffing.sections.positions',
            'occurrences' => fn($q) => $q
                ->where('start_time', '>=', now())
                ->where('status', '!=', 'cancelled')
                ->orderBy('start_time'),
        ])->findOrFail($id);
    }

    /**
     * Format an event as an array for the legacy bot API response.
     */
    public function format(Event $event): array
    {
        if ($event->relationLoaded('occurrences')) {
            $occurrence = $event->occurrences
                ->where('start_time', '>=', now())
                ->where('status', '!=', 'cancelled')
                ->sortBy('start_time')
                ->first();
        } else {
            $occurrence = $event->occurrences()
                ->where('start_time', '>=', now())
                ->where('status', '!=', 'cancelled')
                ->orderBy('start_time')
                ->first();
        }

        $startDatetime = $occurrence ? Carbon::parse($occurrence->start_time) : null;
        $endDatetime   = $occurrence ? Carbon::parse($occurrence->end_time) : null;

        return [
            'id'                 => $event->id,
            'name'               => $event->title,
            'short_description'  => $event->short_description,
            'description'        => $event->long_description,
            'date'               => $startDatetime?->format('Y-m-d'),
            'start_time'         => $startDatetime?->format('H:i'),
            'end_time'           => $endDatetime?->format('H:i'),
            'start_datetime'     => $startDatetime?->toIso8601String(),
            'end_datetime'       => $endDatetime?->toIso8601String(),
            'airports'           => $event->featured_airports ?? [],
            'banner'             => $event->banner_path ? asset('storage/' . $event->banner_path) : null,
            'url'                => url('/events/' . $event->slug),
            'discord_channel_id' => $event->staffing?->discord_channel_id,
            'discord_message_id' => $event->staffing?->discord_message_id,
            'has_staffing'       => $event->staffing !== null,
        ];
    }
}
