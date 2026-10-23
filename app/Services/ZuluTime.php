<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/**
 * Staff and controllers enter times as HH:MM zulu. Events can run past midnight, so
 * a time is read as the first moment at or after a reference point with that clock time.
 *
 * Controllers type their availability in their own time zone, so the helpers take an optional zone.
 * shortcut: minutes are counted on the clock at $start, so a span crossing a DST switch is off by
 * the switch; resolve through local dates if that ever matters.
 */
final class ZuluTime
{
    public const string PATTERN = '/^([01]\d|2[0-3]):[0-5]\d$/';

    /**
     * Minutes from $start until the next time the clock in $timezone reads $time (0–1439).
     */
    public static function minutesAfter(CarbonImmutable $start, string $time, string $timezone = 'UTC'): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));
        $start = $start->setTimezone($timezone);

        return ((($hours * 60 + $minutes) - ($start->hour * 60 + $start->minute)) % 1440 + 1440) % 1440;
    }

    /**
     * The moment at or after $start when the clock in $timezone reads $time.
     */
    public static function at(CarbonImmutable $start, string $time, string $timezone = 'UTC'): CarbonImmutable
    {
        return $start->utc()->startOfMinute()->addMinutes(self::minutesAfter($start, $time, $timezone));
    }

    /**
     * The end of a span that starts at $from: strictly after it, so an end time equal to
     * or earlier than the start rolls over to the next day.
     */
    public static function endAfter(CarbonImmutable $from, string $time, string $timezone = 'UTC'): CarbonImmutable
    {
        $end = self::at($from, $time, $timezone);

        return $end->lessThanOrEqualTo($from) ? $end->addDay() : $end;
    }

    public static function format(CarbonImmutable $moment): string
    {
        return $moment->utc()->format('H:i');
    }
}
