<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'calendar_id' => 'required|exists:calendars,id',
            'title' => 'required|string|max:255',
            'short_description' => 'required|max:280',
            'long_description' => 'required',
            // Note: Changed to 'date' to be flexible with Carbon parsing
            'start_date' => 'required|date', 
            'end_date' => 'required|date|after_or_equal:start_date',
            'event_type' => 'integer',
            'recurrence_interval' => 'nullable|required_if:event_type,1|integer',
            'recurrence_unit' => 'nullable|required_if:event_type,1|string|max:255',
            'recurrence_end_date' => 'nullable|required_if:event_type,1|date|after_or_equal:end_date',
            'image' => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
        ];
    }

    protected function passedValidation()
    {
        // 1. Aspect Ratio Check
        if ($this->hasFile('image')) {
            $image = $this->file('image');
            [$width, $height] = getimagesize($image->getPathName());
            if (abs(($width / $height) - (16 / 9)) > 0.01) {
                throw ValidationException::withMessages([
                    'image' => 'The image must have a 16:9 aspect ratio.',
                ]);
            }
        }

        // 2. Handle Recurrence Cleanup
        $isSingleEvent = $this->event_type == '0';

        // 3. Merge Carbon Objects
        $this->merge([
            'start_date' => Carbon::parse($this->start_date),
            'end_date' => Carbon::parse($this->end_date),
            'recurrence_end_date' => ($isSingleEvent || !$this->recurrence_end_date) 
                ? null 
                : Carbon::parse($this->recurrence_end_date),
            'recurrence_interval' => $isSingleEvent ? null : (int)$this->recurrence_interval,
            'recurrence_unit' => $isSingleEvent ? null : $this->recurrence_unit,
        ]);
    }

    /**
     * Override to return the Carbon objects to the EventService
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated();

        return array_merge($data, [
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'recurrence_end_date' => $this->recurrence_end_date,
            'recurrence_interval' => $this->recurrence_interval,
            'recurrence_unit' => $this->recurrence_unit,
        ]);
    }
}