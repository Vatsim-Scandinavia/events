<?php

namespace App\Actions;

use App\Models\Event;
use App\Models\RosterSlot;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class RosterSchedule
{
    public function __construct(private EventSchedule $schedule) {}

    /** @return array{date: string, starts_at: string|null, ends_at: string|null, status: string, reason: string|null}|null */
    public function currentOrNext(Event $event): ?array
    {
        $now = CarbonImmutable::now('UTC');

        return $this->findScheduled($event, $now->setTimezone($event->timezone)->toDateString(), $now);
    }

    /** @return array{date: string, starts_at: string|null, ends_at: string|null, status: string, reason: string|null}|null */
    public function nextAfter(Event $event, string $date): ?array
    {
        return $this->findScheduled($event, CarbonImmutable::parse($date, 'UTC')->addDay()->toDateString());
    }

    /** @return array{date: string, starts_at: string|null, ends_at: string|null, status: string, reason: string|null}|null */
    private function findScheduled(Event $event, string $from, ?CarbonImmutable $ongoingAt = null): ?array
    {
        $event->loadMissing('cancellations');
        $searchLimit = $event->cancellations->count() + 240;
        for ($examined = 0; $examined < $searchLimit;) {
            $occurrences = $this->schedule->upcoming($event, $from, 12, $examined === 0 ? $ongoingAt : null);
            if ($occurrences === []) {
                return null;
            }
            foreach ($occurrences as $occurrence) {
                $examined++;
                if ($occurrence['status'] === 'scheduled' && ($ongoingAt === null || CarbonImmutable::parse($occurrence['ends_at'])->gt($ongoingAt))) {
                    return $occurrence;
                }
            }
            $nextFrom = CarbonImmutable::parse($occurrences[array_key_last($occurrences)]['date'], 'UTC')->addDay()->toDateString();
            if ($nextFrom <= $from) {
                return null;
            }
            $from = $nextFrom;
        }

        return null;
    }

    /** @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}|null */
    public function timesFor(RosterSlot $slot, string $date, ?Event $event = null): ?array
    {
        $event ??= $slot->shift->roster->event;
        $day = CarbonImmutable::parse($date, 'UTC');
        $start = $this->schedule->resolveLocal($day->addDays($slot->start_day_offset)->toDateString().'T'.$slot->start_time, $event->timezone);
        $end = $this->schedule->resolveLocal($day->addDays($slot->end_day_offset)->toDateString().'T'.$slot->end_time, $event->timezone);
        $occurrence = $this->schedule->occurrence($event, $date);
        if ($start === null || $end === null || ! $end->gt($start) || $occurrence === null
            || $occurrence['starts_at'] === null || $occurrence['ends_at'] === null
            || $start->lt(CarbonImmutable::parse($occurrence['starts_at'])) || $end->gt(CarbonImmutable::parse($occurrence['ends_at']))) {
            return null;
        }

        return ['starts_at' => $start, 'ends_at' => $end];
    }

    /** @return array{start_day_offset: int, start_time: string, end_day_offset: int, end_time: string} */
    public function templateFor(Event $event, string $date, string $start, string $end, string $field): array
    {
        $startUtc = CarbonImmutable::parse($start, 'UTC');
        $endUtc = CarbonImmutable::parse($end, 'UTC');
        $localStart = $startUtc->setTimezone($event->timezone);
        $localEnd = $endUtc->setTimezone($event->timezone);
        $day = CarbonImmutable::parse($date, 'UTC');
        $attributes = [
            'start_day_offset' => (int) $day->diffInDays(CarbonImmutable::parse($localStart->toDateString(), 'UTC')),
            'start_time' => $localStart->format('H:i'),
            'end_day_offset' => (int) $day->diffInDays(CarbonImmutable::parse($localEnd->toDateString(), 'UTC')),
            'end_time' => $localEnd->format('H:i'),
        ];
        $projected = $this->timesFor(new RosterSlot($attributes), $date, $event);
        if ($projected === null || ! $projected['starts_at']->equalTo($startUtc) || ! $projected['ends_at']->equalTo($endUtc)) {
            throw ValidationException::withMessages([$field => 'Choose an unambiguous local time range that can repeat with this event.']);
        }

        return $attributes;
    }
}
