<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Event;

class StoreEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Event::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'short_description' => 'required|string|max:1000',
            'long_description' => 'required|string',
            'featured_airports' => 'nullable|array',
            'featured_airports.*' => 'string|max:4',
            'calendar_id' => 'required|exists:calendars,id',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'banner' => 'nullable|image|mimes:jpeg,jpg,png|max:5120',
            'recurrence_rule' => 'nullable|string',
            'discord_staffing_channel_id' => [
                'nullable',
                'string',
                'max:255',
                'unique:events,discord_staffing_channel_id',
            ],
        ];
    }
}