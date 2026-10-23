<?php

namespace App\Http\Requests;

use App\Models\Fir;
use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * An event as Event Staff enter it on the create and edit pages: its details, plus its
 * schedule (see EventScheduleRequest). Where the event may move to is checked by the fir_id rule.
 */
class EventRequest extends EventScheduleRequest
{
    /**
     * Airports are entered as ICAO codes in any case and spacing. Routes may be missing
     * altogether when the form is sent as multipart (with a banner) and there are none.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'airports' => collect($this->input('airports', []))
                ->map(fn ($code) => strtoupper(trim((string) $code)))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'routes' => collect($this->input('routes', []))
                ->map(fn ($route) => [
                    'from' => strtoupper(trim((string) ($route['from'] ?? ''))),
                    'to' => strtoupper(trim((string) ($route['to'] ?? ''))),
                    'route' => strtoupper(preg_replace('/\s+/', ' ', trim((string) ($route['route'] ?? '')))),
                    // Multipart forms send booleans as "1" and "0".
                    'mandatory' => filter_var($route['mandatory'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'fir_id' => ['required', 'integer', Rule::exists('firs', 'id'), function (string $attribute, mixed $value, Closure $fail) {
                $fir = Fir::find($value);

                if ($fir && Gate::denies('manage-events', $fir)) {
                    $fail('You can only add events to FIRs you manage.');
                }
            }],
            'name' => ['required', 'string', 'max:120'],
            'event_type_id' => ['nullable', 'integer', Rule::exists('event_types', 'id')],
            'description' => ['nullable', 'string', 'max:5000'],
            'airports' => ['required', 'array', 'min:1', 'max:12'],
            'airports.*' => ['string', 'regex:/^[A-Z0-9]{4}$/'],
            'routes' => ['present', 'array', 'max:20'],
            'routes.*.from' => ['required', 'string', 'regex:/^[A-Z0-9]{4}$/'],
            'routes.*.to' => ['required', 'string', 'regex:/^[A-Z0-9]{4}$/'],
            'routes.*.route' => ['required', 'string', 'max:1000'],
            'routes.*.mandatory' => ['boolean'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192', 'dimensions:min_width=600'],
            'remove_banner' => ['sometimes', 'boolean'],
            'booking_url' => ['nullable', 'url:https,http', 'max:255'],
            'briefing_url' => ['nullable', 'url:https,http', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'fir_id.required' => 'Pick the FIR that runs the event.',
            'fir_id.integer' => 'Pick the FIR that runs the event.',
            'fir_id.exists' => 'Pick the FIR that runs the event.',
            'airports.required' => 'Add at least one airport.',
            'airports.*.regex' => 'Use four-letter ICAO codes, like EKCH.',
            'routes.*.from.*' => 'Use a four-letter ICAO code.',
            'routes.*.to.*' => 'Use a four-letter ICAO code.',
            'routes.*.route.required' => 'Enter the route, or remove the row.',
            'banner.image' => 'Upload a JPG, PNG, WebP or GIF image.',
            'banner.mimes' => 'Upload a JPG, PNG, WebP or GIF image.',
            'banner.max' => 'The banner can be at most 8 MB.',
            'banner.uploaded' => 'The banner couldn’t be uploaded. Try a smaller image (8 MB at most).',
            'banner.dimensions' => 'The banner should be at least 600 pixels wide.',
            '*_url.url' => 'Enter a full link starting with https://.',
        ];
    }

    /**
     * The event's own fields, ready to save.
     *
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return [
            ...$this->safe()->only(['fir_id', 'name', 'event_type_id', 'description', 'airports', 'booking_url', 'briefing_url']),
            'routes' => $this->validated('routes') ?: null,
        ];
    }
}
