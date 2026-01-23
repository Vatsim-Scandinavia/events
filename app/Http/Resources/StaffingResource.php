<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffingResource extends JsonResource
{
    protected $targetOccurrenceDate;

    public function __construct($resource, $targetOccurrenceDate = null)
    {
        parent::__construct($resource);
        $this->targetOccurrenceDate = $targetOccurrenceDate;
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
            'title' => $this->title,
            'discord' => [
                'channel_id' => $this->discord_staffing_channel_id,
                'message_id' => $this->discord_staffing_message_id,
            ],
            'staffing' => $this->staffings->map(function ($staffing) {
                return new StaffingGroupResource(
                    $staffing, 
                    $this->targetOccurrenceDate, 
                    $this->resource
                );
            })
        ];
    }
}
