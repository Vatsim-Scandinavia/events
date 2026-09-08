<?php

namespace App\Http\Requests;

use App\Models\Airport;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AirportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Event::class) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'icao' => ['bail', 'required', 'string', 'regex:/\A[A-Z]{4}\z/', Rule::unique(Airport::class)],
            'name' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'icao.regex' => 'The ICAO code must be exactly 4 letters (A-Z).',
            'icao.unique' => 'This airport already exists. Look up its ICAO code to select it.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('icao'))) {
            $this->merge(['icao' => strtoupper(trim($this->input('icao')))]);
        }
    }
}
