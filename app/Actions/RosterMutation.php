<?php

namespace App\Actions;

use App\Models\Event;
use App\Models\EventRoster;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RosterMutation
{
    public function __construct(private EventSchedule $schedule) {}

    /** Acquire a write lock before any transactional reads, including on SQLite. */
    public function lockEvent(int $eventId): Event
    {
        DB::table('events')->where('id', $eventId)->update(['id' => DB::raw('id')]);

        return Event::whereKey($eventId)->lockForUpdate()->firstOrFail();
    }

    public function lockRoster(EventRoster $roster): EventRoster
    {
        $event = $this->lockEvent($roster->event_id);
        $locked = EventRoster::whereKey($roster->id)->lockForUpdate()->firstOrFail();
        $locked->setRelation('event', $event);

        return $locked;
    }

    /** @return array{date: string, starts_at: string|null, ends_at: string|null, status: string, reason: string|null} */
    public function occurrence(Event $event, string $date): array
    {
        $occurrence = $this->schedule->occurrence($event, $date);
        if ($occurrence === null || $occurrence['status'] !== 'scheduled') {
            throw ValidationException::withMessages(['roster' => 'This event occurrence is unavailable or cancelled.']);
        }
        if (! CarbonImmutable::parse($occurrence['ends_at'])->isFuture()) {
            throw ValidationException::withMessages(['roster' => 'This event occurrence has already ended.']);
        }

        return $occurrence;
    }

    /** @param array{starts_at: string|null, ends_at: string|null} $occurrence */
    public function validateRange(string $start, string $end, array $occurrence, string $field): void
    {
        $startsAt = CarbonImmutable::parse($start, 'UTC');
        $endsAt = CarbonImmutable::parse($end, 'UTC');
        if (! $endsAt->gt($startsAt) || $startsAt->lt(CarbonImmutable::parse($occurrence['starts_at']))
            || $endsAt->gt(CarbonImmutable::parse($occurrence['ends_at']))) {
            throw ValidationException::withMessages([$field => 'The time range must be within this event occurrence and end after it starts.']);
        }
    }
}
