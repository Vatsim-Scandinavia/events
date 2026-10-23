<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Formats moments for display in the viewer's time zone. UTC is shown the VATSIM way, with
 * a "z" after the time; any other zone gets its abbreviation, such as "CEST".
 *
 * Times people type stay in UTC: see ZuluTime.
 */
final class DisplayTime
{
    public function __construct(public readonly string $timezone = 'UTC') {}

    public static function for(?User $user): self
    {
        return new self($user?->displayTimezone() ?? 'UTC');
    }

    public function isZulu(): bool
    {
        return $this->timezone === 'UTC';
    }

    public function local(CarbonImmutable $moment): CarbonImmutable
    {
        return $moment->setTimezone($this->timezone);
    }

    /**
     * The clock time, HH:MM, without a zone.
     */
    public function time(CarbonImmutable $moment): string
    {
        return $this->local($moment)->format('H:i');
    }

    /**
     * What follows a time to say which zone it is in: "z", or " CEST". Zones without a
     * common abbreviation get their offset, such as " UTC+03".
     */
    public function suffix(CarbonImmutable $moment): string
    {
        if ($this->isZulu()) {
            return 'z';
        }

        $abbreviation = $this->local($moment)->format('T');

        return ' '.(preg_match('/^[+-]\d/', $abbreviation) ? 'UTC'.$abbreviation : $abbreviation);
    }

    /**
     * A span of time, such as "19:00–22:00 CEST" or "17:00–20:00z".
     */
    public function range(CarbonImmutable $from, CarbonImmutable $until): string
    {
        return $this->time($from).'–'.$this->time($until).$this->suffix($from);
    }

    public function date(CarbonImmutable $moment, string $format = 'D j M'): string
    {
        return $this->local($moment)->format($format);
    }

    /**
     * A date and time, such as "Sat 18 Oct, 19:00 CEST".
     */
    public function moment(CarbonImmutable $moment): string
    {
        return $this->date($moment, 'D j M, H:i').$this->suffix($moment);
    }

    /**
     * A length of time, such as "3h 30m", "2h" or "45m".
     */
    public static function duration(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return trim(($hours > 0 ? "{$hours}h " : '').($rest > 0 ? "{$rest}m" : ''));
    }
}
