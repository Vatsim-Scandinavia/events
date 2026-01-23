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
            'id' => $this->id,
            'title' => $this->title,
            'short_description' => $this->short_description,
            'long_description' => $this->long_description,
            'start_datetime' => $this->start_datetime->toIso8601String(),
            'end_datetime' => $this->end_datetime->toIso8601String(),
            'featured_airports' => $this->featured_airports ?? [],
            'banner' => $this->banner ? asset('storage/' . $this->banner) : null,
            'uri' => url('/events/' . $this->id),

            $this->mergeWhen($isAuthenticated, [
                'discord' => [
                    'channel_id' => $this->discord_staffing_channel_id,
                    'message_id' => $this->discord_staffing_message_id,
                ]
            ]),
        ];

        return parent::toArray($request);
    }
}
