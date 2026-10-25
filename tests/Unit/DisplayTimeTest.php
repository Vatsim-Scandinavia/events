<?php

namespace Tests\Unit;

use App\Services\DisplayTime;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class DisplayTimeTest extends TestCase
{
    public function test_utc_is_shown_as_zulu(): void
    {
        $display = new DisplayTime;
        $start = CarbonImmutable::parse('2026-07-18 17:00', 'UTC');

        $this->assertSame('17:00–20:00z', $display->range($start, $start->addHours(3)));
        $this->assertSame('Sat 18 Jul, 17:00z', $display->moment($start));
    }

    public function test_other_zones_follow_daylight_saving_and_show_their_abbreviation(): void
    {
        $display = new DisplayTime('Europe/Copenhagen');
        $summer = CarbonImmutable::parse('2026-07-18 17:00', 'UTC');
        $winter = CarbonImmutable::parse('2026-12-18 17:00', 'UTC');

        $this->assertSame('19:00–22:00 CEST', $display->range($summer, $summer->addHours(3)));
        $this->assertSame('18:00–21:00 CET', $display->range($winter, $winter->addHours(3)));
    }

    public function test_a_late_zulu_event_falls_on_the_next_day_further_east(): void
    {
        $display = new DisplayTime('Asia/Tokyo');
        $start = CarbonImmutable::parse('2026-07-18 23:00', 'UTC');

        $this->assertSame('Sun 19 Jul', $display->date($start));
        $this->assertSame('Sun 19 Jul, 08:00 JST', $display->moment($start));
    }

    public function test_zones_without_an_abbreviation_show_their_offset(): void
    {
        $display = new DisplayTime('Asia/Dubai');

        $this->assertSame(' UTC+04', $display->suffix(CarbonImmutable::parse('2026-07-18 17:00', 'UTC')));
    }
}
