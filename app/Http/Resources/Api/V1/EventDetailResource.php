<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Event;
use App\Services\EventDescription;
use Illuminate\Http\Request;

/**
 * The full event, with its description and the positions staff want covered.
 *
 * @mixin Event
 */
class EventDetailResource extends EventResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            /** Markdown, as staff wrote it. */
            'description' => $this->description,
            /** The description rendered to sanitised HTML. */
            'descriptionHtml' => EventDescription::toHtml($this->description),
            'positions' => PositionResource::collection($this->positions),
        ];
    }
}
