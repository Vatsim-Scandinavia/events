<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffingGroupResource extends JsonResource
{
    protected $targetOccurrenceDate;
    protected $event;

    /**
     * @param mixed $resource The primary resource (e.g., a Collection or Model).
     * @param mixed \Carbon\Carbon|null $targetOccurrenceDate The target occurrence date for the staffing positions.
     * @param mixed \App\Models\Event|null $event The associated event model.
     */
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
        $targetDate = $this->targetOccurrenceDate;
        $event = $this->event;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'positions' => $this->positions->map(function ($position) use ($targetDate, $event) {
                return new StaffingPositionResource($position, $targetDate, $event);
            }),
        ];
    }
}
