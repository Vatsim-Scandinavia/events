<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalendarResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'title'         => $this->title,
            'description'   => $this->description,
            'visibility'    => $this->visibility,
            'creator'       => $this->whenLoaded('creator', fn() => [
                'id'        => $this->creator->id,
                'full_name' => $this->creator->full_name,
            ]),
            'events'        => EventResource::collection($this->whenLoaded('events')),
        ];
    }
}
