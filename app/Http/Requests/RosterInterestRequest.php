<?php

namespace App\Http\Requests;

class RosterInterestRequest extends RosterOccurrenceRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'position_ids' => ['required', 'array', 'min:1', 'max:200'],
            'position_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'availability' => ['required', 'array', 'min:1', 'max:20'],
            'availability.*' => ['array:starts_at,ends_at'],
            'availability.*.starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'availability.*.ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:availability.*.starts_at'],
        ];
    }
}
