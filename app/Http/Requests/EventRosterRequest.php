<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventRosterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('event')) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'occurrence_date' => ['required', 'date_format:Y-m-d'],
            'mode' => ['required', Rule::in(['pre_slotted', 'open_interest'])],
            'is_open' => ['required', 'boolean'],
            'shifts' => ['present', 'array', 'max:20'],
            'shifts.*' => ['array:id,name,slots'],
            'shifts.*.id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'shifts.*.name' => ['required', 'string', 'max:100'],
            'shifts.*.slots' => ['present', 'array', 'max:100'],
            'shifts.*.slots.*' => ['array:id,callsign,starts_at,ends_at'],
            'shifts.*.slots.*.id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'shifts.*.slots.*.callsign' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9]+(?:_[A-Z0-9]+)+$/'],
            'shifts.*.slots.*.starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'shifts.*.slots.*.ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:shifts.*.slots.*.starts_at'],
            'positions' => ['present', 'array', 'max:200'],
            'positions.*' => ['array:id,callsign'],
            'positions.*.id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'positions.*.callsign' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9]+(?:_[A-Z0-9]+)+$/', 'distinct'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['occurrence_date' => $this->route('date')]);
        $shifts = $this->input('shifts');
        if (is_array($shifts)) {
            foreach ($shifts as &$shift) {
                if (! is_array($shift)) {
                    continue;
                }
                if (is_string($shift['name'] ?? null)) {
                    $shift['name'] = trim($shift['name']);
                }
                if (is_array($shift['slots'] ?? null)) {
                    foreach ($shift['slots'] as &$slot) {
                        if (is_array($slot) && is_string($slot['callsign'] ?? null)) {
                            $slot['callsign'] = mb_strtoupper(trim($slot['callsign']));
                        }
                    }
                    unset($slot);
                }
            }
            unset($shift);
            $this->merge(['shifts' => $shifts]);
        }
        $positions = $this->input('positions');
        if (is_array($positions)) {
            foreach ($positions as &$position) {
                if (is_array($position) && is_string($position['callsign'] ?? null)) {
                    $position['callsign'] = mb_strtoupper(trim($position['callsign']));
                }
            }
            unset($position);
            $this->merge(['positions' => $positions]);
        }
    }
}
