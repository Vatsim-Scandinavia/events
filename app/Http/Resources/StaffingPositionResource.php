<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class StaffingPositionResource extends JsonResource
{
    protected $targetOccurrenceDate;
    protected $event;
    protected $section;

    public function __construct($resource, $targetOccurrenceDate = null, $event = null, $section = null)
    {
        parent::__construct($resource);
        $this->targetOccurrenceDate = $targetOccurrenceDate;
        $this->event = $event;
        $this->section = $section;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $event = $this->event ?? $this->staffing->event;
        $targetDate = $this->targetOccurrenceDate ?? $event->start_datetime;

        $startTime = null;
        $endTime = null;
        $startDatetime = null;
        $endDatetime = null;
        if ($targetDate) {
            $baseDate = is_string($targetDate) ? Carbon::parse($targetDate) : $targetDate;
            $start = $this->start_time ?? $event->start_datetime->format('H:i:s');
            $end = $this->end_time ?? $event->end_datetime->format('H:i:s');
            $startTime = $baseDate->copy()->setTimeFromTimeString($start)->format('Y-m-d H:i:s');
            $endTime = $baseDate->copy()->setTimeFromTimeString($end)->format('Y-m-d H:i:s');
            $startDatetime = $baseDate->copy()->setTimeFromTimeString($start)->toIso8601String();
            $endDatetime = $baseDate->copy()->setTimeFromTimeString($end)->toIso8601String();
        }

        return [
            'section' => $this->section,
            'callsign' => $this->position_id ?? null,
            'discord_user' => $this->discord_user_id ?? null,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'start_datetime' => $startDatetime,
            'end_datetime' => $endDatetime,
        ];
    }
}
