<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class StaffingPositionResource extends JsonResource
{
    protected $targetOccurrenceDate;
    protected $event;

    public function __construct($resource, $targetOccurrenceDate = null, $event = null)
    {
        parent::__construct($resource);
        $this->targetOccurrenceDate = $targetOccurrenceDate;
        $this->event = $event;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get the actual event model, not the wrapped resource
        $event = $this->event;
        if ($event instanceof JsonResource) {
            $event = $event->resource;
        }
        if (!$event) {
            $event = $this->staffing->event;
        }

        $targetDate = $this->targetOccurrenceDate ?? $event->start_datetime;

        // Handle case where targetOccurrenceDate is 0 or falsy
        if (!$targetDate || $targetDate === 0) {
            $targetDate = $event->start_datetime;
        }


        $startDatetime = null;
        $endDatetime = null;

        if ($targetDate) {
            // Ensure we have a Carbon instance

            $baseDate = $targetDate instanceof Carbon ? $targetDate : Carbon::parse($targetDate);


            $startTime = $this->start_time ?? $event->start_datetime->format('H:i:s');
            $endTime = $this->end_time ?? $event->end_datetime->format('H:i:s');

            $startDatetime = $baseDate->copy()->setTimeFromTimeString($startTime)->toIso8601String();
            $endDatetime = $baseDate->copy()->setTimeFromTimeString($endTime)->toIso8601String();
        }

        return [
            'id' => $this->id,
            'position_name' => $this->position_name,
            'position_id' => $this->position_id,
            'discord_user' => $this->discord_user,
            'section' => $this->section,
            'start_datetime' => $startDatetime,
            'end_datetime' => $endDatetime,
            'is_local' => $this->is_local,
            'booked' => $this->isBooked(),
        ];
    }
}
