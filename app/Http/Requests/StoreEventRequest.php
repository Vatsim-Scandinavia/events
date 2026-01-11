<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'calendar_id' => 'required|exists:calendars,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'event_type' => 'required|boolean',
            'recurrence_interval' => 'nullable|integer|required_if:event_type,1',
            'recurrence_unit' => 'nullable|string|required_if:event_type,1',
            'recurrence_end_date' => 'nullable|date|required_if:event_type,1|after_or_equal:end_date',
            'short_description' => 'required|max:280',
            'long_description' => 'required',
            'image' => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
        ];
    }

    /**
     * Massage the data after validation passes.
     */
    protected function passedValidation()
    {
        $isSingleEvent = $this->event_type == '0';

        $this->merge([
            'user_id' => auth()->id(),
            'start_date' => Carbon::parse($this->start_date),
            'end_date' => Carbon::parse($this->end_date),
            'recurrence_end_date' => ($isSingleEvent || !$this->recurrence_end_date) 
                ? null 
                : Carbon::parse($this->recurrence_end_date),
            'recurrence_interval' => $isSingleEvent ? null : $this->recurrence_interval,
            'recurrence_unit' => $isSingleEvent ? null : $this->recurrence_unit,
        ]);
    }

    /**
     * Override the validated method to include merged/transformed data.
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated();

        return array_merge($data, [
            'user_id' => $this->user_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'recurrence_end_date' => $this->recurrence_end_date,
            'recurrence_interval' => $this->recurrence_interval,
            'recurrence_unit' => $this->recurrence_unit,
        ]);
    }
}