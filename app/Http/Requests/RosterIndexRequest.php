<?php

namespace App\Http\Requests;

use App\Models\EventRoster;
use Illuminate\Foundation\Http\FormRequest;

class RosterIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', EventRoster::class) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
