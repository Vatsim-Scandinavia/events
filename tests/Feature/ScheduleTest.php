<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    public function test_the_daily_jobs_never_run_twice_at_once(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->mapWithKeys(fn (Event $event) => [trim(str($event->command)->after('artisan')->replace(["'", '"'], '')) => $event]);

        foreach (['events:extend-repeats', 'users:sync-vatsim'] as $command) {
            $this->assertArrayHasKey($command, $events->all());
            $this->assertTrue($events[$command]->withoutOverlapping, "{$command} can overlap.");
            $this->assertSame('0 0 * * *', $events[$command]->expression);
        }
    }
}
