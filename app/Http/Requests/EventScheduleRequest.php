<?php

namespace App\Http\Requests;

use App\Enums\RepeatInterval;
use App\Models\Event;
use App\Staffing\EventSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * When an event takes place, as Event Staff set it on the create and edit pages. The form
 * also sends this on its own, to preview the dates before saving.
 */
class EventScheduleRequest extends FormRequest
{
    /**
     * Editing an event needs the right to manage events in the FIR that runs it now.
     */
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event ? Gate::allows('update', $event) : true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date_format:Y-m-d H:i'],
            'ends_at' => ['required', 'date_format:Y-m-d H:i'],
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'repeats' => ['required', Rule::enum(RepeatInterval::class)],
            'repeat_ends' => ['exclude_if:repeats,none', 'required', 'in:never,count,until'],
            'repeat_count' => ['exclude_if:repeats,none', 'exclude_unless:repeat_ends,count', 'required', 'integer', 'min:2', 'max:'.EventSchedule::MAX_DATES],
            'repeat_until' => ['exclude_if:repeats,none', 'exclude_unless:repeat_ends,until', 'required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'starts_at.*' => 'Pick when the event starts.',
            'ends_at.*' => 'Pick when the event ends.',
            'repeat_count.*' => 'Repeat between 2 and '.EventSchedule::MAX_DATES.' times.',
            'repeat_until.*' => 'Pick the last date.',
            'timezone.*' => 'Pick a time zone from the list.',
        ];
    }

    /**
     * How the event repeats, ready to save on the event.
     *
     * @return array{repeats: RepeatInterval, repeat_count: int|null, repeat_until: string|null}
     */
    public function repeatRule(): array
    {
        return [
            'repeats' => RepeatInterval::from($this->validated('repeats')),
            'repeat_count' => $this->validated('repeat_count'),
            'repeat_until' => $this->validated('repeat_until'),
        ];
    }

    /**
     * The first date's start and end, which the event itself keeps, typed in the event's
     * time zone (UTC unless staff picked another).
     *
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, timezone: string}
     */
    public function firstSpan(): array
    {
        $timezone = $this->validated('timezone') ?? 'UTC';

        return [
            'starts_at' => CarbonImmutable::parse($this->validated('starts_at'), $timezone)->utc(),
            'ends_at' => CarbonImmutable::parse($this->validated('ends_at'), $timezone)->utc(),
            'timezone' => $timezone,
        ];
    }

    /**
     * The dates this schedule gives the event (see EventSchedule::plan()).
     *
     * @return Collection<int, array{id: int|null, starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     *
     * @throws ValidationException
     */
    public function plan(EventSchedule $schedule, ?Event $event): Collection
    {
        $rule = $this->repeatRule();
        $first = $this->firstSpan();

        return $schedule->plan($event, $first['starts_at'], $first['ends_at'], $first['timezone'], $rule['repeats'], $rule['repeat_count'], $rule['repeat_until']);
    }
}
