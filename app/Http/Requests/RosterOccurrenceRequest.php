<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RosterOccurrenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('roster')) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['return_to_current' => ['sometimes', 'boolean'],
            'occurrence_date' => ['required', 'date_format:Y-m-d']];
    }
}
