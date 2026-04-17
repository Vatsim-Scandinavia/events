<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStaffingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage events');
    }

    public function rules(): array
    {
        return [
            'event_id'           => ['required', 'exists:events,id'],
            'discord_channel_id' => ['nullable', 'string', 'regex:/^\d{17,19}$/'],
            'discord_message_id' => ['nullable', 'string'],
        ];
    }
}
