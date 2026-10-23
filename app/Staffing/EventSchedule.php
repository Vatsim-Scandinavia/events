<?php

namespace App\Staffing;

use App\Enums\RepeatInterval;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\PositionShift;
use App\Models\StaffingInterestWindow;
use App\Services\DisplayTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Saves when an event takes place: a first date, one local start time in the event's
 * time zone shared by every date (stored in UTC), and how often it repeats. Each date is an occurrence. Dates that have
 * already started are left alone; the rest are moved, added or removed to match.
 *
 * Moving a date keeps its staffing: shifts are offsets from the start so they follow
 * on their own, and the roster and controllers' availability move by the same amount.
 */
final class EventSchedule
{
    /**
     * The most dates an event can be set to repeat on.
     */
    public const int MAX_DATES = 52;

    /**
     * How far ahead the dates of an event that repeats indefinitely are created.
     * The `events:extend-repeats` command tops them up every day.
     */
    public const int HORIZON_MONTHS = 6;

    /**
     * The longest a single date of an event can last, in hours.
     */
    public const int MAX_HOURS = 72;

    /**
     * Work out the dates to save from the first date's start and end and how the event
     * repeats, or fail with errors keyed to the form's fields. With neither a count nor
     * a last date, the event repeats indefinitely and dates are planned HORIZON_MONTHS ahead.
     *
     * Generated dates keep the existing occurrence on the same day, so its staffing
     * stays put; any left over move to the remaining dates in order. Dates that have
     * already started are never touched, and past dates are not created.
     *
     * @return Collection<int, array{id: int|null, starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     *
     * @throws ValidationException
     */
    public function plan(?Event $event, CarbonImmutable $first, CarbonImmutable $firstEnd, string $timezone, RepeatInterval $repeats, ?int $count, ?string $until): Collection
    {
        // Days and start times are worked out in the event's zone, so dates keep their local time across DST.
        $first = $first->setTimezone($timezone);
        $localDay = fn (CarbonImmutable $moment) => $moment->setTimezone($timezone)->toDateString();
        $duration = (int) $first->diffInMinutes($firstEnd, false);
        // A series already under way keeps its first date; only its upcoming dates change.
        $underWay = $event && $event->starts_at->lessThanOrEqualTo(now()) && $localDay($event->starts_at) === $first->toDateString();

        if ($duration <= 0) {
            $this->fail('ends_at', 'The event has to end after it starts.');
        }

        if ($duration > self::MAX_HOURS * 60) {
            $this->fail('ends_at', 'An event can last at most '.self::MAX_HOURS.' hours.');
        }

        if ($first->lessThanOrEqualTo(now()) && ! $underWay) {
            $this->fail('starts_at', $first->format('D j M, H:i').($timezone === 'UTC' ? 'z' : ' '.$first->format('T')).' has already passed. Pick a later start.');
        }

        if (! $repeats->fits($first)) {
            $this->fail('repeats', 'That doesn’t repeat well from this date. Pick another interval.');
        }

        $days = $this->days($first, $repeats, $count, $until);

        $existing = $event ? $event->occurrences()->get() : collect();
        [$locked, $upcoming] = $existing->partition(fn (EventOccurrence $occurrence) => $occurrence->hasStarted());
        $lockedDays = $locked->map(fn (EventOccurrence $occurrence) => $localDay($occurrence->starts_at));

        $wanted = $days
            ->map(fn (CarbonImmutable $day) => $day->setTimeFrom($first))
            ->reject(fn (CarbonImmutable $startsAt) => $lockedDays->contains($startsAt->toDateString()) || $startsAt->lessThanOrEqualTo(now()))
            ->values();

        if ($event && $wanted->isNotEmpty()) {
            $this->ensureShiftsFit($event, $duration);
        }

        // Keep occurrences on days that are still wanted, then hand the rest out in order.
        // A cancelled date never moves, so its cancellation can't land on another day.
        $byDay = $upcoming->keyBy(fn (EventOccurrence $occurrence) => $localDay($occurrence->starts_at));
        $spare = $upcoming
            ->reject(fn (EventOccurrence $occurrence) => $occurrence->cancelled_at !== null)
            ->reject(fn (EventOccurrence $occurrence) => $wanted->contains(fn ($startsAt) => $startsAt->toDateString() === $localDay($occurrence->starts_at)))
            ->values();

        $planned = $wanted->map(function (CarbonImmutable $localStart) use ($byDay, $spare, $duration) {
            $occurrence = $byDay->get($localStart->toDateString()) ?? $spare->shift();
            $startsAt = $localStart->utc();
            $endsAt = $startsAt->addMinutes($duration);

            if ($occurrence) {
                $this->ensureRosterFits($occurrence, $startsAt, $endsAt);
            }

            return ['id' => $occurrence?->id, 'starts_at' => $startsAt, 'ends_at' => $endsAt];
        });

        return $planned;
    }

    /**
     * The days the event falls on, from the first one.
     *
     * @return Collection<int, CarbonImmutable>
     */
    private function days(CarbonImmutable $first, RepeatInterval $repeats, ?int $count, ?string $until): Collection
    {
        $first = $first->startOfDay();

        if ($repeats === RepeatInterval::None) {
            return collect([$first]);
        }

        $indefinitely = $count === null && $until === null;
        $last = match (true) {
            $indefinitely => now($first->getTimezone())->addMonthsNoOverflow(self::HORIZON_MONTHS)->startOfDay()->max($first),
            $until !== null => CarbonImmutable::parse($until, $first->getTimezone()),
            default => null,
        };

        if ($last !== null && $last->lessThan($first)) {
            $this->fail('repeat_until', 'The last date can’t be before the first.');
        }

        $days = collect();

        for ($step = 0; $count === null || $step < $count; $step++) {
            $day = $repeats->after($first, $step);

            if ($last !== null && $day->greaterThan($last)) {
                break;
            }

            if (! $indefinitely && $days->count() === self::MAX_DATES) {
                $this->fail('repeat_until', 'That’s more than '.self::MAX_DATES.' dates. Pick an earlier last date.');
            }

            $days->push($day);
        }

        return $days;
    }

    /**
     * Apply a plan from plan(): move existing dates, add new ones and remove the
     * upcoming ones that were left out. Call inside a transaction.
     *
     * @param  Collection<int, array{id: int|null, starts_at: CarbonImmutable, ends_at: CarbonImmutable}>  $planned
     */
    public function save(Event $event, Collection $planned): void
    {
        $existing = $event->occurrences()->get()->keyBy('id');

        $this->removed($existing, $planned)->each->delete();

        foreach ($planned as $date) {
            if ($date['id'] === null) {
                $event->occurrences()->create(['starts_at' => $date['starts_at'], 'ends_at' => $date['ends_at']]);
            } else {
                $this->move($existing[$date['id']], $date['starts_at'], $date['ends_at']);
            }
        }
    }

    /**
     * The upcoming dates a plan leaves out, which save() deletes along with their staffing.
     *
     * @param  Collection<int, EventOccurrence>  $existing
     * @param  Collection<int, array{id: int|null, starts_at: CarbonImmutable, ends_at: CarbonImmutable}>  $planned
     * @return Collection<int, EventOccurrence>
     */
    public function removed(Collection $existing, Collection $planned): Collection
    {
        $keep = $planned->pluck('id')->filter();

        return $existing->reject(fn (EventOccurrence $occurrence) => $occurrence->hasStarted() || $keep->contains($occurrence->id))->values();
    }

    private function move(EventOccurrence $occurrence, CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        $delta = (int) $occurrence->starts_at->diffInMinutes($startsAt, false);

        if ($delta === 0 && $occurrence->ends_at->eq($endsAt)) {
            return;
        }

        if ($delta !== 0) {
            foreach ($occurrence->rosterEntries as $entry) {
                $entry->update(['starts_at' => $entry->starts_at->addMinutes($delta), 'ends_at' => $entry->ends_at->addMinutes($delta)]);
            }
        }

        // Availability moves with the date, trimmed to a shorter event.
        StaffingInterestWindow::query()
            ->whereIn('staffing_interest_id', $occurrence->interests()->select('id'))
            ->get()
            ->each(function (StaffingInterestWindow $window) use ($delta, $endsAt) {
                $from = $window->starts_at->addMinutes($delta);
                $until = $window->ends_at->addMinutes($delta)->min($endsAt);

                $from->lessThan($until)
                    ? $window->update(['starts_at' => $from, 'ends_at' => $until])
                    : $window->delete();
            });

        $occurrence->update(['starts_at' => $startsAt, 'ends_at' => $endsAt]);
    }

    private function ensureShiftsFit(Event $event, int $duration): void
    {
        $latest = PositionShift::query()
            ->whereIn('event_position_id', $event->positions()->select('id'))
            ->max('ends_offset');

        if ($latest !== null && $latest > $duration) {
            $this->fail('ends_at', 'Some shifts run '.DisplayTime::duration((int) $latest).' into the event. Shorten them on the staffing page first.');
        }
    }

    private function ensureRosterFits(EventOccurrence $occurrence, CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        $delta = (int) $occurrence->starts_at->diffInMinutes($startsAt, false);
        $overruns = $occurrence->rosterEntries->contains(fn ($entry) => $entry->ends_at->addMinutes($delta)->greaterThan($endsAt));

        if ($overruns) {
            $this->fail('ends_at', 'The roster for '.$occurrence->starts_at->utc()->format('D j M').' runs past the new end time. Change the roster first.');
        }
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
