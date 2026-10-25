<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Event;
use App\Models\EventOccurrence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Narrowing a listing of events or their dates by FIR, event type and a date range.
 * Without a range, only dates that haven't ended count.
 */
class EventFilterRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** The ICAO code of the FIR running the event, e.g. EKDK. */
            'fir' => ['nullable', 'string', 'max:255'],
            /** The name of the event type. */
            'type' => ['nullable', 'string', 'max:255'],
            /** Only dates that end on or after this day (UTC). Defaults to now. */
            'from' => ['nullable', 'date_format:Y-m-d'],
            /** Only dates that start on or before this day (UTC). */
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            /** Results per page, at most 100. */
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Keeps events run by the requested FIR and of the requested type.
     *
     * @param  Builder<Event>  $events
     * @return Builder<Event>
     */
    public function filterEvents(Builder $events): Builder
    {
        return $events
            ->when($this->validated('fir'), fn (Builder $query, string $icao) => $query->whereRelation('fir', 'icao', $icao))
            ->when($this->validated('type'), fn (Builder $query, string $type) => $query->whereRelation('eventType', 'name', $type));
    }

    /**
     * Keeps dates within the requested range, or those that haven't ended without one.
     *
     * @param  Builder<EventOccurrence>  $occurrences
     * @return Builder<EventOccurrence>
     */
    public function filterOccurrences(Builder $occurrences): Builder
    {
        $from = $this->validated('from');
        $to = $this->validated('to');

        return $occurrences
            ->where('ends_at', '>=', $from !== null ? CarbonImmutable::parse($from, 'UTC') : now())
            ->when($to, fn (Builder $query, string $to) => $query->where('starts_at', '<', CarbonImmutable::parse($to, 'UTC')->addDay()));
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 25);
    }
}
