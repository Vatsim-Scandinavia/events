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
        // Use passed event or fallback to relationship
        $event = $this->event ?? $this->staffing->event;
        
        // Use passed targetOccurrenceDate (important for recurring events) or fallback to event start
        $targetDate = $this->targetOccurrenceDate ?? $event->start_datetime;

        // Calculate start/end times
        $startDatetime = null;
        $endDatetime = null;

        if ($targetDate) {
            // Handle start_time independently
            if ($this->start_time) {
                $startDatetime = Carbon::parse($targetDate->format('Y-m-d') . ' ' . $this->start_time);
            } else {
                // Fallback to event start
                $startDatetime = $targetDate;
            }
        
            // Handle end_time independently
            if ($this->end_time) {
                $endDatetime = Carbon::parse($targetDate->format('Y-m-d') . ' ' . $this->end_time);
            } else {
                // Fallback to event end
                $endDatetime = $event->end_datetime;
            }
        }

        return [
            'id' => $this->id,
            'callsign' => $this->position_id,
            'name' => $this->position_name,
            'start_time' => $startDatetime?->format('H:i'),
            'end_time' => $endDatetime?->format('H:i'),
            'booked' => $this->isBooked(),
            'user' => $this->formatUser(),
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
