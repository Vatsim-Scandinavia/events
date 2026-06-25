<?php

namespace Tests\Feature;

use App\Jobs\SendPreEventReminders;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendPreEventRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-25 10:00:00', 'UTC'));

        config()->set('services.discord.webhook_url', 'https://discord.test/webhooks/reminders');
        config()->set('services.discord.mention_role_id', null);

        Http::fake([
            'https://discord.test/*' => Http::response(['ok' => true], 200),
        ]);

        Queue::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_sends_reminder_for_recurring_occurrence_when_parent_started_in_past(): void
    {
        $occurrenceStart = now()->addHours(2);

        $event = $this->createRecurringEvent(
            $occurrenceStart->copy()->subWeek(),
            'FREQ=WEEKLY;COUNT=3'
        );

        $this->dispatchJob();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://discord.test/webhooks/reminders'
            && $request['embeds'][0]['timestamp'] === $occurrenceStart->toIso8601String()
            && str_contains($request['content'], 'starting in 2 hours'));

        $this->assertContains(
            $occurrenceStart->toISOString(),
            $event->fresh()->notified_occurrences
        );
    }

    public function test_sends_reminder_for_long_running_recurring_event(): void
    {
        $occurrenceStart = now()->addHours(2);

        $this->createRecurringEvent(
            $occurrenceStart->copy()->subYears(3),
            'FREQ=DAILY'
        );

        $this->dispatchJob();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['embeds'][0]['timestamp'] === $occurrenceStart->toIso8601String());
    }

    public function test_does_not_send_duplicate_reminder_for_already_notified_occurrence(): void
    {
        $occurrenceStart = now()->addHours(2);

        $event = $this->createRecurringEvent(
            $occurrenceStart->copy()->subWeek(),
            'FREQ=WEEKLY;COUNT=3',
            ['notified_occurrences' => [$occurrenceStart->toIso8601String()]]
        );

        $this->dispatchJob();

        Http::assertSentCount(0);
        $this->assertCount(1, $event->fresh()->notified_occurrences);
    }

    public function test_does_not_send_reminder_for_cancelled_occurrence(): void
    {
        $occurrenceStart = now()->addHours(2);

        $event = $this->createRecurringEvent(
            $occurrenceStart->copy()->subWeek(),
            'FREQ=WEEKLY;COUNT=3',
            ['cancelled_occurrences' => [$occurrenceStart->toISOString()]]
        );

        $this->dispatchJob();

        Http::assertSentCount(0);
        $this->assertEmpty($event->fresh()->notified_occurrences);
    }

    private function createRecurringEvent(Carbon $start, string $recurrenceRule, array $overrides = []): Event
    {
        return Event::factory()->create(array_merge([
            'start_datetime' => $start,
            'end_datetime' => $start->copy()->addHours(2),
            'recurrence_rule' => $recurrenceRule,
            'notified_occurrences' => [],
            'cancelled_occurrences' => [],
        ], $overrides));
    }

    private function dispatchJob(): void
    {
        $this->app->call([new SendPreEventReminders, 'handle']);
    }
}
