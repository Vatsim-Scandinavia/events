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

    public function test_a_span_gives_the_date_and_says_the_day_it_ends_when_that_is_another(): void
    {
        $display = new DisplayTime;
        $evening = CarbonImmutable::parse('2026-10-18 19:00', 'UTC');
        $late = CarbonImmutable::parse('2026-10-18 22:00', 'UTC');

        $this->assertSame('Sun 18 Oct 2026, 19:00–22:00z', $display->span($evening, $evening->addHours(3)));
        $this->assertSame('Sun 18 Oct 2026, 22:00–Mon 19 Oct 02:00z', $display->span($late, $late->addHours(4)));
    }

    public function test_a_span_crosses_midnight_where_the_viewer_is(): void
    {
        $display = new DisplayTime('Europe/Copenhagen');
        $start = CarbonImmutable::parse('2026-10-18 20:00', 'UTC');

        $this->assertSame('Sun 18 Oct 2026, 22:00–Mon 19 Oct 01:00 CEST', $display->span($start, $start->addHours(3)));
    }
}
