<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Event;

class UpdateEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('event'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'calendar_id'       => 'required|exists:calendars,id',
            'title'             => 'required|string|max:255',
            'short_description' => 'required|string|max:1000',
            'long_description'  => 'required|string',
            'featured_airports' => 'nullable|array',
            'featured_airports.*' => 'string|max:4',
            'start_datetime'    => 'required|date',
            'end_datetime'      => 'required|date|after:start_datetime',
            'banner'            => 'nullable|image|mimes:jpeg,jpg,png|max:5120',
            'recurrence_rule'   => 'nullable|string',
            'discord_staffing_channel_id' => [
                'nullable', 
                'string', 
                'max:255', 
                Rule::unique('events')->ignore($this->event->id),
            ],
        ];
    }
}
