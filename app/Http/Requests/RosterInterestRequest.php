<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RosterInterestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('participate', $this->route('roster')) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'position_ids' => ['required', 'array', 'min:1', 'max:200'],
            'position_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'availability' => ['required', 'array', 'min:1', 'max:20'],
            'availability.*' => ['array:starts_at,ends_at'],
            'availability.*.starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'availability.*.ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:availability.*.starts_at'],
        ];
    }
}
