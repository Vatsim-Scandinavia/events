<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Use the next upcoming occurrence, or fall back to the most recent past
        // occurrence so that past events and events with no future occurrences
        // can still be edited with pre-populated date fields.
        // Use the eager-loaded relation when available (e.g. index) to avoid N+1.
        if ($this->relationLoaded('occurrences')) {
            $now = now();
            $nextOccurrence = $this->occurrences
                ->where('start_time', '>=', $now)
                ->where('status', '!=', 'cancelled')
                ->sortBy('start_time')
                ->first();
            $representativeOccurrence = $nextOccurrence
                ?? $this->occurrences->sortByDesc('start_time')->first();
        } else {
            $nextOccurrence = $this->occurrences()
                ->where('start_time', '>=', now())
                ->where('status', '!=', 'cancelled')
                ->orderBy('start_time')
                ->first();
            $representativeOccurrence = $nextOccurrence
                ?? $this->occurrences()->orderByDesc('start_time')->first();
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'long_description' => $this->long_description,
            'featured_airports' => $this->featured_airports,
            'status' => $this->status,
            'banner_url' => $this->banner_path
                ? '/storage/' . $this->banner_path
                : null,
            'recurrence_rule' => $this->recurrence_rule,
            'discord_channel_id' => $this->whenLoaded('staffing', fn() => $this->staffing?->discord_channel_id),
            'staffing' => $this->whenLoaded('staffing', function () {
                $staffing = $this->staffing;
                if (!$staffing) return null;

                return [
                    'id'                 => $staffing->id,
                    'discord_channel_id' => $staffing->discord_channel_id,
                    'discord_message_id' => $staffing->discord_message_id,
                    'sections'           => $staffing->relationLoaded('sections')
                        ? $staffing->sections->map(fn($section) => [
                            'id'        => $section->id,
                            'title'     => $section->title,
                            'order'     => $section->order,
                            'positions' => $section->relationLoaded('positions')
                                ? $section->positions->map(fn($pos) => [
                                    'id'            => $pos->id,
                                    'position_id'   => $pos->position_id,
                                    'position_name' => $pos->position_name,
                                    'start_time'    => $pos->start_time ? substr($pos->start_time, 0, 5) : null,
                                    'end_time'      => $pos->end_time ? substr($pos->end_time, 0, 5) : null,
                                    'is_local_booking' => $pos->is_local_booking,
                                    'order'         => $pos->order,
                                ])->values()
                                : [],
                        ])->values()
                        : [],
                ];
            }),
            'calendar' => [
                'id' => $this->calendar->id,
                'title' => $this->calendar->title,
            ],
            'start_datetime' => $representativeOccurrence ? [
                'display' => Carbon::parse($representativeOccurrence->start_time, 'UTC')->format('Y-m-d\TH:i:s\Z'),
                'local'   => Carbon::parse($representativeOccurrence->start_time)->setTimezone($this->timezone ?? 'UTC')->format('Y-m-d\TH:i:s'),
            ] : null,
            'end_datetime' => $representativeOccurrence ? [
                'display' => Carbon::parse($representativeOccurrence->end_time, 'UTC')->format('Y-m-d\TH:i:s\Z'),
                'local'   => Carbon::parse($representativeOccurrence->end_time)->setTimezone($this->timezone ?? 'UTC')->format('Y-m-d\TH:i:s'),
            ] : null,
            'timezone' => $this->timezone ?? 'UTC',
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'occurrences' => EventOccurrenceResource::collection(
                $request->user()?->can('manage events')
                    ? $this->futureOccurrences
                    : $this->futureOccurrences->where('status', '!=', 'cancelled')->values()
            ),
        ];
    }
}
