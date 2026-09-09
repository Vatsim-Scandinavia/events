<?php

namespace App\Http\Requests;

use App\Actions\EventSchedule;
use App\Models\Airport;
use App\Models\Event;
use App\Models\Team;
use App\PermissionName;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');

        return $event instanceof Event
            ? ($this->user()?->can('update', $event) ?? false)
            : ($this->user()?->can('create', Event::class) ?? false);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'owner_team_id' => ['required', 'integer', Rule::exists(Team::class, 'id')],
            'roster_enabled' => ['sometimes', 'boolean'],
            'title' => ['required', 'string', 'max:255'],
            'short_description' => ['required', 'string', 'max:500'],
            'description' => ['required', 'string', 'max:50000'],
            'airport_ids' => ['required', 'array', 'min:1', 'max:50'],
            'airport_ids.*' => ['required', 'integer', 'distinct', Rule::exists(Airport::class, 'id')],
            'timezone' => ['required', 'timezone:all'],
            'local_start' => ['required', 'date_format:Y-m-d\TH:i'],
            'local_end' => ['required', 'date_format:Y-m-d\TH:i', 'after:local_start'],
            'recurrence' => ['required', Rule::in(['none', 'weekly', 'monthly'])],
            'recurrence_interval' => ['required', 'integer', 'min:1', 'max:52'],
            'monthly_week' => ['nullable', 'required_if:recurrence,monthly', 'integer', Rule::in([-1, 1, 2, 3, 4, 5])],
            'recurrence_until' => ['nullable', 'date_format:Y-m-d'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
            'remove_banner' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $event = $this->route('event');
            $owner = Team::findOrFail($this->integer('owner_team_id'));
            if ($event instanceof Event ? $owner->id !== $event->owner_team_id : ! $this->user()?->can(PermissionName::ManageEvents, $owner)) {
                $validator->errors()->add('owner_team_id', 'Choose an FIR you coordinate. An existing event cannot change its owner FIR.');
            }

            $schedule = app(EventSchedule::class);
            $start = $schedule->resolveLocal($this->string('local_start')->toString(), $this->string('timezone')->toString());
            $end = $schedule->resolveLocal($this->string('local_end')->toString(), $this->string('timezone')->toString());
            foreach (['local_start' => $start, 'local_end' => $end] as $field => $time) {
                if ($time === null) {
                    $validator->errors()->add($field, 'This local time does not exist because the clocks change. Choose another time.');
                }
            }
            if ($start !== null && $end !== null && ! $end->gt($start)) {
                $validator->errors()->add('local_end', 'The end time must be after the start time in the selected timezone.');
            }

            $date = CarbonImmutable::parse(substr($this->string('local_start')->toString(), 0, 10), 'UTC');
            if ($this->input('recurrence') === 'monthly') {
                $week = $this->integer('monthly_week');
                $matches = $week === -1 ? $date->addWeek()->month !== $date->month : (int) ceil($date->day / 7) === $week;
                if (! $matches) {
                    $validator->errors()->add('monthly_week', 'The weekday position must match the first start date.');
                }
            }
            if ($this->input('recurrence') !== 'none' && $this->filled('recurrence_until') && $this->string('recurrence_until')->toString() < $date->toDateString()) {
                $validator->errors()->add('recurrence_until', 'The series end date must be on or after the first occurrence.');
            }
        }];
    }
}
