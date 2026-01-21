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
        $isAuthenticated = $request->attributes->get('api_key');
        
        return [
            'event_id' => $this->id,
            'title' => $this->title,
            'short_description' => $this->short_description,
            'description' => $this->long_description,
            'date' => $this->start_datetime->format('Y-m-d'),
            'start_time' => $this->start_datetime->format('H:i'),
            'end_time' => $this->end_datetime->format('H:i'),
            'start_datetime' => $this->start_datetime->toIso8601String(),
            'end_datetime' => $this->end_datetime->toIso8601String(),
            'airports' => $this->featured_airports ?? [],
            'banner' => $this->banner_path ? asset('storage/' . $this->banner_path) : null,
            'url' => url('/events/' . $this->id),
            
            $this->mergeWhen($isAuthenticated, [
                'discord_channel_id' => $this->discord_staffing_channel_id,
                'discord_message_id' => $this->discord_staffing_message_id,
            ]),
        ];
    }
}