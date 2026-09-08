<?php

namespace Tests\Feature;

use App\Actions\EventSchedule;
use App\Models\Event;
use App\Models\EventCancellation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class EventScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_other_sunday_retains_local_times_across_autumn_dst(): void
    {
        $event = Event::factory()->weekly(2)->create([
            'timezone' => 'Europe/Copenhagen', 'local_start' => '2026-10-18T18:00', 'local_end' => '2026-10-18T21:00',
        ]);

        $dates = app(EventSchedule::class)->upcoming($event, '2026-10-01', 3);

        $this->assertSame(['2026-10-18', '2026-11-01', '2026-11-15'], array_column($dates, 'date'));
        $this->assertSame(['2026-10-18T16:00:00+00:00', '2026-11-01T17:00:00+00:00', '2026-11-15T17:00:00+00:00'], array_column($dates, 'starts_at'));
        $this->assertSame('2026-11-01T20:00:00+00:00', $dates[1]['ends_at']);
    }

    public function test_spring_dst_keeps_wall_times_and_overnight_end_dates(): void
    {
        $event = Event::factory()->weekly()->create([
            'timezone' => 'Europe/Copenhagen', 'local_start' => '2026-03-22T23:00', 'local_end' => '2026-03-23T02:00',
        ]);

        $dates = app(EventSchedule::class)->upcoming($event, '2026-03-22', 2);

        $this->assertSame('2026-03-22T22:00:00+00:00', $dates[0]['starts_at']);
        $this->assertSame('2026-03-29T21:00:00+00:00', $dates[1]['starts_at']);
        $this->assertSame('2026-03-30T00:00:00+00:00', $dates[1]['ends_at']);
    }

    #[TestWith([1, ['2026-02-08', '2026-03-08', '2026-04-12']])]
    #[TestWith([3, ['2026-02-08', '2026-05-10', '2026-08-09']])]
    public function test_monthly_and_quarterly_schedules_use_weekday_position(int $interval, array $expected): void
    {
        $event = Event::factory()->monthly($interval, 2)->create(['local_start' => '2026-02-08T18:00', 'local_end' => '2026-02-08T21:00']);

        $dates = app(EventSchedule::class)->upcoming($event, '2026-02-01', 3);

        $this->assertSame($expected, array_column($dates, 'date'));
    }

    public function test_last_sunday_and_fifth_sunday_are_distinct_patterns(): void
    {
        $last = Event::factory()->monthly(1, -1)->create(['local_start' => '2026-03-29T18:00', 'local_end' => '2026-03-29T21:00']);
        $fifth = Event::factory()->monthly(1, 5)->create(['local_start' => '2026-03-29T18:00', 'local_end' => '2026-03-29T21:00']);

        $this->assertSame(['2026-03-29', '2026-04-26', '2026-05-31'], array_column(app(EventSchedule::class)->upcoming($last, '2026-03-01', 3), 'date'));
        $this->assertSame(['2026-03-29', '2026-05-31', '2026-08-30'], array_column(app(EventSchedule::class)->upcoming($fifth, '2026-03-01', 3), 'date'));
    }

    public function test_nonexistent_wall_times_are_skipped_and_repeated_times_use_the_first_occurrence(): void
    {
        $schedule = app(EventSchedule::class);
        $event = Event::factory()->weekly()->create(['timezone' => 'Europe/Copenhagen', 'local_start' => '2026-03-22T02:30', 'local_end' => '2026-03-22T04:00']);

        $dates = $schedule->upcoming($event, '2026-03-22', 3);

        $this->assertSame('skipped', $dates[1]['status']);
        $this->assertNull($dates[1]['starts_at']);
        $this->assertSame('scheduled', $dates[2]['status']);
        $this->assertSame('2026-10-25T00:30:00+00:00', $schedule->resolveLocal('2026-10-25T02:30', 'Europe/Copenhagen')?->toIso8601String());
    }

    public function test_end_date_is_inclusive_and_cancellation_does_not_shift_the_series(): void
    {
        $event = Event::factory()->weekly(2)->create(['recurrence_until' => '2026-11-01']);
        EventCancellation::factory()->for($event)->create(['occurrence_date' => '2026-10-18']);

        $dates = app(EventSchedule::class)->upcoming($event, '2026-10-04');

        $this->assertSame(['2026-10-04', '2026-10-18', '2026-11-01'], array_column($dates, 'date'));
        $this->assertSame(['scheduled', 'cancelled', 'scheduled'], array_column($dates, 'status'));
        $this->assertSame('Insufficient staffing', $dates[1]['reason']);
        $this->assertNull(app(EventSchedule::class)->occurrence($event, '2026-10-11'));
        $this->assertNull(app(EventSchedule::class)->occurrence($event, '2026-11-15'));
    }

    public function test_one_off_events_and_far_future_pagination_are_bounded(): void
    {
        $event = Event::factory()->create();
        $weekly = Event::factory()->weekly(2)->create();

        $this->assertCount(1, app(EventSchedule::class)->upcoming($event, '2026-01-01'));
        $this->assertSame([], app(EventSchedule::class)->upcoming($event, '2026-10-05'));
        $this->assertCount(12, app(EventSchedule::class)->upcoming($weekly, '2040-01-01'));
    }
}
