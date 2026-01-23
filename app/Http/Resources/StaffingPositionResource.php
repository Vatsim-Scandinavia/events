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
        $event = $this->event ?? $this->staffing->event;

        $targetDate = $this->targetOccurrenceDate ?? $event->start_datetime;

        $startDatetime = null;
        $endDatetime = null;

        if ($targetDate) {
            $baseDate = is_string($targetDate) ? Carbon::parse($targetDate) : $targetDate;

            $startTime = $this->start_time ?? $event->start_datetime->format('H:i:s');
            $endTime = $this->end_time ?? $event->end_datetime->format('H:i:s');

            $startDatetime = $baseDate->copy()->setTimeFromTimeString($startTime)->toIso8601String();
            $endDatetime = $baseDate->copy()->setTimeFromTimeString($endTime)->toIso8601String();
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'required_certifications' => $this->required_certifications ?? [],
            'start_datetime' => $startDatetime,
            'end_datetime' => $endDatetime,
            'booked' => $this->isBooked(),
            'booked_by' => $this->formatUser(),
        ];
    }

    protected function formatUser(): ?array
    {
        if ($this->bookedBy) {
            return [
                'id' => $this->bookedBy->id,
                'name' => $this->bookedBy->name,
            ];
        }

        if ($this->vatsim_cid) {
            return [
                'id' => $this->vatsim_cid,
                'name' => "CID {$this->vatsim_cid}",
            ];
        }

        return null;
    }
}
