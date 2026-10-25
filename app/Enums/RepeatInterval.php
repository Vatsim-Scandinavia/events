<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * How often an event repeats after its first date.
 */
enum RepeatInterval: string
{
    case None = 'none';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    // The same weekday in the same week of every month, e.g. the second Saturday.
    case MonthlyWeekday = 'monthly_weekday';
    // The last of that weekday in every month, e.g. the last Friday.
    case MonthlyLastWeekday = 'monthly_last_weekday';

    /**
     * The date $steps repeats after $first, or null when that month has no such day.
     */
    public function after(CarbonImmutable $first, int $steps): ?CarbonImmutable
    {
        return match ($this) {
            self::None => $steps === 0 ? $first : null,
            self::Weekly => $first->addWeeks($steps),
            self::Biweekly => $first->addWeeks($steps * 2),
            self::MonthlyWeekday => $first->startOfMonth()->addMonthsNoOverflow($steps)
                ->nthOfMonth((int) ceil($first->day / 7), $first->dayOfWeek) ?: null,
            self::MonthlyLastWeekday => $first->startOfMonth()->addMonthsNoOverflow($steps)
                ->lastOfMonth($first->dayOfWeek),
        };
    }

    /**
     * In words, for a series starting on $first (in the event's time zone), e.g. "Every 2 weeks on Saturday".
     */
    public function describe(CarbonImmutable $first): ?string
    {
        $weekday = $first->format('l');
        $ordinals = [1 => 'first', 2 => 'second', 3 => 'third', 4 => 'fourth'];

        return match ($this) {
            self::None => null,
            self::Weekly => "Every week on {$weekday}",
            self::Biweekly => "Every 2 weeks on {$weekday}",
            self::MonthlyWeekday => 'Monthly on the '.$ordinals[(int) ceil($first->day / 7)]." {$weekday}",
            self::MonthlyLastWeekday => "Monthly on the last {$weekday}",
        };
    }

    /**
     * Whether the interval makes sense for a first date. A monthly "fifth Saturday"
     * would skip most months, so only "last Saturday" is offered for those dates.
     */
    public function fits(CarbonImmutable $first): bool
    {
        return match ($this) {
            self::MonthlyWeekday => $first->day <= 28,
            self::MonthlyLastWeekday => $first->day > $first->daysInMonth - 7,
            default => true,
        };
    }
}
