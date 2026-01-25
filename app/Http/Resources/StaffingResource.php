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
        $sectionTitlesAssoc = collect(range(1, 4))
            ->mapWithKeys(fn($i) => [
                "section_{$i}_title" => $this->staffings[$i - 1]->name ?? null
            ])->all();

        $positions = $this->staffings->flatMap(function ($staffing, $i) use ($request) {
            $sectionNumber = $i + 1;
            return $staffing->positions->map(function ($position) use ($staffing, $sectionNumber, $request) {
                return (new \App\Http\Resources\StaffingPositionResource(
                    $position,
                    $this->targetOccurrenceDate,
                    $staffing->event,
                    $staffing->order ?? $sectionNumber
                ))->toArray($request);
            });
        })->values();

        return [
            'id' => $this->id,
            'description' => $this->staffing_description ?? null,
            'channel_id' => $this->discord_staffing_channel_id,
            'message_id' => $this->discord_staffing_message_id,
            ...$sectionTitlesAssoc,
            'event_id' => $this->event_id ?? $this->id,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
            'positions' => $positions->all(),
            'event' => new \App\Http\Resources\EventResource($this),
        ];
    }
}
