<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventOccurrenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'event_id' => $this->event_id,
            'start_time' => $this->start_time
                ? Carbon::parse($this->start_time, 'UTC')->format('Y-m-d\TH:i:s\Z')
                : null,
            'end_time' => $this->end_time
                ? Carbon::parse($this->end_time, 'UTC')->format('Y-m-d\TH:i:s\Z')
                : null,
            'status'   => $this->status,
        ];
    }
}
