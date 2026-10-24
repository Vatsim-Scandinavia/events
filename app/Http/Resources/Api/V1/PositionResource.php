<?php

namespace App\Http\Resources\Api\V1;

use App\Models\EventPosition;
use App\Models\PositionShift;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A position staff want covered, with its shifts as minutes from the start of each date.
 *
 * @mixin EventPosition
 */
class PositionResource extends JsonResource
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
            'callsign' => $this->callsign,
            'frequency' => $this->frequency,
            /** @var list<array{id: int, startOffset: int, endOffset: int}> */
            'shifts' => $this->shifts->map(fn (PositionShift $shift) => [
                'id' => $shift->id,
                'startOffset' => $shift->starts_offset,
                'endOffset' => $shift->ends_offset,
            ])->all(),
        ];
    }
}
