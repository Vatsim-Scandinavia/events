<?php

namespace App\Http\Requests;

use App\Rules\ValidRecurrenceRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    /** Maximum banner upload size in kilobytes. */
    public const BANNER_MAX_KB = 5120;
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage events');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'calendar_id'          => ['required', 'exists:calendars,id'],
            'title'                => ['required', 'string', 'max:255'],
            'short_description'    => ['nullable', 'string', 'max:500'],
            'long_description'     => ['nullable', 'string', 'max:65535'],
            'featured_airports'    => ['nullable', 'array', 'max:20'],
            'featured_airports.*'  => ['string', 'regex:/^[A-Z]{4}$/'],
            'status'               => ['required', 'in:draft,published'],
            'start_datetime'       => ['required', 'date_format:Y-m-d\TH:i:s'],
            'end_datetime'         => ['required', 'date_format:Y-m-d\TH:i:s', 'after:start_datetime'],
            'timezone'             => ['required', 'timezone'],
            'recurrence_rule'      => ['nullable', 'string', new ValidRecurrenceRule],
            'discord_channel_id'   => ['nullable', 'string', 'regex:/^\d{17,19}$/'],
            'banner'               => ['nullable', 'image', 'max:' . self::BANNER_MAX_KB],
        ];
    }
}
