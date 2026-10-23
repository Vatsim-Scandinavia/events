<?php

namespace Tests\Unit;

use App\Services\ZuluTime;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ZuluTimeTest extends TestCase
{
    public function test_minutes_after_wraps_past_midnight(): void
    {
        $start = CarbonImmutable::parse('2026-11-10 22:00', 'UTC');

        $this->assertSame(0, ZuluTime::minutesAfter($start, '22:00'));
        $this->assertSame(90, ZuluTime::minutesAfter($start, '23:30'));
        $this->assertSame(150, ZuluTime::minutesAfter($start, '00:30'));
        $this->assertSame(1380, ZuluTime::minutesAfter($start, '21:00'));
    }

    public function test_end_after_rolls_to_the_next_day_when_needed(): void
    {
        $from = CarbonImmutable::parse('2026-11-10 23:00', 'UTC');

        $this->assertSame('2026-11-11 01:00', ZuluTime::endAfter($from, '01:00')->format('Y-m-d H:i'));
        $this->assertSame('2026-11-11 23:00', ZuluTime::endAfter($from, '23:00')->format('Y-m-d H:i'));
        $this->assertSame('2026-11-10 23:45', ZuluTime::endAfter($from, '23:45')->format('Y-m-d H:i'));
    }
}
