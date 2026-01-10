<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'calendar_id' => 'required|exists:calendars,id',
            'title' => 'required|string|max:255',
            'short_description' => 'required|max:280',
            'long_description' => 'required',
            'start_date' => 'required|date_format:Y-m-d H:i|after_or_equal:today',
            'end_date' => 'required|date_format:Y-m-d H:i|after_or_equal:start_date',
            'event_type' => 'integer',
            'recurrence_interval' => 'nullable|required_if:event_type,1|integer',
            'recurrence_unit' => 'nullable|required_if:event_type,1|string|max:255',
            'recurrence_end_date' => 'nullable|required_if:event_type,1|date_format:Y-m-d H:i|after_or_equal:end_date',
            'image' => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
        ];
    }

    protected function passedValidation()
    {
        if ($this->hasFile('image')) {
            $image = $this->file('image');
            [$width, $height] = getimagesize($image->getPathName());
            
            // Use an epsilon/tolerance for floats to avoid rounding math issues
            if (abs(($width / $height) - (16 / 9)) > 0.01) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'image' => 'The image must have a 16:9 aspect ratio.',
                ]);
            }
        }
    }
}
