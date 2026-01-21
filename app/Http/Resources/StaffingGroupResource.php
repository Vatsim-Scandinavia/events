<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class StaffingGroupResource extends JsonResource
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
        return [
            'id' => $this->id,
            'name' => $this->name,
            'positions' => $this->positions->sortBy('order')->map(function ($position) {
                return new StaffingPositionResource($position, $this->targetOccurrenceDate, $this->event);
            })->values(),
        ];
    }
}
