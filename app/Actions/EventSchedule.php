<?php

namespace App\Actions;

use App\Models\Event;
use Carbon\CarbonImmutable;
use DateTimeZone;

class EventSchedule
{
    /**
     * Resolve a wall time without silently moving a time in a DST gap.
     * A repeated wall time uses its first chronological occurrence.
     */
    public function resolveLocal(string $local, string $timezone): ?CarbonImmutable
    {
        $wall = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $local, 'UTC');
        if ($wall === null || $wall->format('Y-m-d\TH:i') !== $local) {
            return null;
        }

        $zone = new DateTimeZone($timezone);
        $transitions = $zone->getTransitions($wall->getTimestamp() - 172800, $wall->getTimestamp() + 172800);
        $offsets = array_column($transitions, 'offset');
        $candidates = [];

        foreach (array_unique($offsets) as $offset) {
            $candidate = $wall->subSeconds($offset);
            if ($candidate->setTimezone($timezone)->format('Y-m-d\TH:i') === $local) {
                $candidates[] = $candidate;
            }
        }

        usort($candidates, fn (CarbonImmutable $left, CarbonImmutable $right): int => $left->getTimestamp() <=> $right->getTimestamp());

        return $candidates[0] ?? null;
    }

    /** @return array{date: string, starts_at: string|null, ends_at: string|null, status: string, reason: string|null}|null */
    public function occurrence(Event $event, string $date): ?array
    {
        $first = CarbonImmutable::parse(substr($event->local_start, 0, 10), 'UTC');
        $day = CarbonImmutable::parse($date, 'UTC');
        if ($day->lt($first) || ($event->recurrence_until !== null && $date > $event->recurrence_until->toDateString())) {
            return null;
        }

        $days = (int) $first->diffInDays($day);
        if ($event->recurrence === 'none' && $days !== 0) {
            return null;
        }
        if ($event->recurrence === 'weekly' && $days % (7 * $event->recurrence_interval) !== 0) {
            return null;
        }
        if ($event->recurrence === 'monthly') {
            $months = ($day->year - $first->year) * 12 + $day->month - $first->month;
            $expected = $this->monthlyDate($first, $months, $event->monthly_week ?? 1);
            if ($months % $event->recurrence_interval !== 0 || $expected?->toDateString() !== $date) {
                return null;
            }
        }

        $endDayOffset = (int) $first->diffInDays(CarbonImmutable::parse(substr($event->local_end, 0, 10), 'UTC'));
        $start = $this->resolveLocal($date.substr($event->local_start, 10), $event->timezone);
        $end = $this->resolveLocal($day->addDays($endDayOffset)->toDateString().substr($event->local_end, 10), $event->timezone);
        $cancellation = $event->cancellations->firstWhere('occurrence_date', $date);
        $skipped = $start === null || $end === null || ! $end->gt($start);

        return [
            'date' => $date,
            'starts_at' => $start?->toIso8601String(),
            'ends_at' => $end?->toIso8601String(),
            'status' => $event->status === 'cancelled' || $cancellation !== null ? 'cancelled' : ($skipped ? 'skipped' : 'scheduled'),
            'reason' => $event->status === 'cancelled' ? $event->cancellation_reason : ($cancellation->reason ?? ($skipped ? 'This local time does not exist because the clocks change.' : null)),
        ];
    }

    /** @return list<array{date: string, starts_at: string|null, ends_at: string|null, status: string, reason: string|null}> */
    public function upcoming(Event $event, string $from, int $limit = 12): array
    {
        $first = CarbonImmutable::parse(substr($event->local_start, 0, 10), 'UTC');
        $after = CarbonImmutable::parse($from, 'UTC');
        $event->loadMissing('cancellations');
        $index = 0;

        if ($after->gt($first)) {
            $index = match ($event->recurrence) {
                'weekly' => max(0, (int) floor($first->diffInDays($after) / (7 * $event->recurrence_interval))),
                'monthly' => max(0, intdiv(($after->year - $first->year) * 12 + $after->month - $first->month, $event->recurrence_interval)),
                default => 0,
            };
        }

        $occurrences = [];
        for ($attempt = 0; $attempt < 240 && count($occurrences) < $limit; $attempt++, $index++) {
            $day = match ($event->recurrence) {
                'weekly' => $first->addWeeks($index * $event->recurrence_interval),
                'monthly' => $this->monthlyDate($first, $index * $event->recurrence_interval, $event->monthly_week ?? 1),
                default => $index === 0 ? $first : null,
            };

            if ($day !== null) {
                if ($day->year > 9999 || ($event->recurrence_until !== null && $day->toDateString() > $event->recurrence_until->toDateString())) {
                    break;
                }
                if ($day->gte($after) && ($occurrence = $this->occurrence($event, $day->toDateString())) !== null) {
                    $occurrences[] = $occurrence;
                }
            }
            if ($event->recurrence === 'none') {
                break;
            }
        }

        return $occurrences;
    }

    private function monthlyDate(CarbonImmutable $first, int $months, int $week): ?CarbonImmutable
    {
        $month = $first->startOfMonth()->addMonths($months);
        if ($week === -1) {
            $last = $month->endOfMonth()->startOfDay();

            return $last->subDays(($last->dayOfWeek - $first->dayOfWeek + 7) % 7);
        }

        $day = $month->addDays(($first->dayOfWeek - $month->dayOfWeek + 7) % 7 + ($week - 1) * 7);

        return $day->month === $month->month ? $day : null;
    }
}
