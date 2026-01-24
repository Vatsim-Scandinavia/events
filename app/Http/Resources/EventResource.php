<?php

namespace App\Http\Resources;

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

        return [
            'id' => $this->id,
            'title' => $this->title,
            'short_description' => $this->short_description,
            'long_description' => $this->long_description,
            'start_datetime' => $this->start_datetime->toIso8601String(),
            'end_datetime' => $this->end_datetime->toIso8601String(),
            'start_date' => $this->start_datetime->toDateString('Y-m-d H:i:s'),
            'end_date' => $this->end_datetime->toDateString('Y-m-d H:i:s'),
            'featured_airports' => $this->featured_airports ?? [],
            'banner' => $this->banner ? asset('storage/' . $this->banner) : null,
            'uri' => url('/events/' . $this->id),
        ];
    }
}
