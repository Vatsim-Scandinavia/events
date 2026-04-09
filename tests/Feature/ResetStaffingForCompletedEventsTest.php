<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ResetStaffingForCompletedEvents;
use App\Models\Event;
use App\Models\Staffing;
use App\Models\StaffingPosition;
use App\Services\ControlCenterService;
use App\Services\DiscordBotService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResetStaffingForCompletedEventsTest extends TestCase
{
    use RefreshDatabase;

    private ControlCenterService $controlCenter;
    private DiscordBotService $discordBot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controlCenter = Mockery::mock(ControlCenterService::class);
        $this->discordBot = Mockery::mock(DiscordBotService::class);

        $this->app->instance(ControlCenterService::class, $this->controlCenter);
        $this->app->instance(DiscordBotService::class, $this->discordBot);
    }

    #[Test]
    public function it_resets_staffing_when_occurrence_has_just_ended(): void
    {
        $event = $this->createRecurringEvent(
            start: now()->subHours(3),
            end: now()->subHour(),
        );

        $position = $this->createBookedPosition($event);

        $this->discordBot->shouldReceive('dispatchStaffingUpdate')
            ->once()
            ->with(Mockery::on(fn($e) => $e->id === $event->id), 'update', true);

        $this->controlCenter->shouldReceive('deleteBooking')
            ->once()
            ->with(1);

        $this->dispatchJob();

        $position->refresh();

        $this->assertNull($position->booked_by_user_id);
        $this->assertNull($position->discord_user_id);
        $this->assertNull($position->vatsim_cid);
        $this->assertNull($position->control_center_booking_id);
        $this->assertNotNull($event->fresh()->last_staffing_reset_at);
    }

    #[Test]
    public function it_does_not_reset_if_occurrence_has_not_ended_yet(): void
    {
        $event = $this->createRecurringEvent(
            start: now()->subHour(),
            end: now()->addHour(),
        );

        $this->createBookedPosition($event);

        $this->discordBot->shouldNotReceive('dispatchStaffingUpdate');
        $this->controlCenter->shouldNotReceive('deleteBooking');

        $this->dispatchJob();

        $this->assertNull($event->fresh()->last_staffing_reset_at);
    }

    #[Test]
    public function it_does_not_reset_if_already_reset_for_this_occurrence(): void
    {
        $occurrenceEnd = now()->subHours(2);

        $event = $this->createRecurringEvent(
            start: now()->subHours(4),
            end: $occurrenceEnd,
            lastResetAt: $occurrenceEnd,
        );

        $this->createBookedPosition($event);

        $this->discordBot->shouldNotReceive('dispatchStaffingUpdate');
        $this->controlCenter->shouldNotReceive('deleteBooking');

        $this->dispatchJob();
    }

    #[Test]
    public function it_does_not_reset_if_outside_48h_window(): void
    {
        $event = $this->createRecurringEvent(
            start: now()->subHours(72), // base occurrence well outside window
            end: now()->subHours(49),   // ended 49h ago, outside 48h window
        );

        $this->createBookedPosition($event);

        $this->discordBot->shouldNotReceive('dispatchStaffingUpdate');
        $this->controlCenter->shouldNotReceive('deleteBooking');

        $this->dispatchJob();
    }

    #[Test]
    public function it_stores_occurrence_end_time_not_current_time_after_reset(): void
    {
        $occurrenceEnd = now()->subHours(2)->startOfMinute();

        $event = $this->createRecurringEvent(
            start: now()->subHours(4),
            end: $occurrenceEnd,
        );

        $this->createBookedPosition($event);

        $this->discordBot->shouldReceive('dispatchStaffingUpdate')->once();
        $this->controlCenter->shouldReceive('deleteBooking')->once()->with(1);

        $this->dispatchJob();

        $resetAt = $event->fresh()->last_staffing_reset_at->startOfMinute();

        $this->assertTrue($resetAt->equalTo($occurrenceEnd));
    }

    #[Test]
    public function it_skips_events_without_discord_channel(): void
    {
        $event = $this->createRecurringEvent(
            start: now()->subHours(3),
            end: now()->subHour(),
            discordChannelId: null,
        );

        // Force null in case the factory has a default
        $event->update(['discord_staffing_channel_id' => null]);

        $this->createBookedPosition($event);

        $this->discordBot->shouldNotReceive('dispatchStaffingUpdate');
        $this->controlCenter->shouldNotReceive('deleteBooking');

        $this->dispatchJob();
    }

    #[Test]
    public function it_continues_processing_other_events_if_one_fails(): void
    {
        $failingEvent = $this->createRecurringEvent(
            start: now()->subHours(3),
            end: now()->subHour(),
        );

        $successEvent = $this->createRecurringEvent(
            start: now()->subHours(3),
            end: now()->subHour(),
        );

        $this->createBookedPosition($failingEvent);
        $this->createBookedPosition($successEvent);

        $this->discordBot->shouldReceive('dispatchStaffingUpdate')
            ->twice()
            ->andThrow(new \Exception('Discord unavailable'));

        $this->controlCenter->shouldReceive('deleteBooking')->with(1)->andReturn(true);

        $this->dispatchJob();
    }

    #[Test]
    public function it_handles_failed_control_center_deletion_gracefully(): void
    {
        $event = $this->createRecurringEvent(
            start: now()->subHours(3),
            end: now()->subHour(),
        );

        $this->createBookedPosition($event);

        $this->controlCenter->shouldReceive('deleteBooking')
            ->once()
            ->with(1)
            ->andThrow(new \Exception('CC unavailable'));

        $this->discordBot->shouldReceive('dispatchStaffingUpdate')
            ->once()
            ->with(Mockery::any(), 'update', true);

        $this->dispatchJob();

        $this->assertNotNull($event->fresh()->last_staffing_reset_at);
    }

    // --- Helpers ---

    private function dispatchJob(): void
    {
        app(ResetStaffingForCompletedEvents::class)->handle(
            app(\App\Services\EventService::class),
            $this->controlCenter,
            $this->discordBot,
        );
    }

    private function createRecurringEvent(
        Carbon $start,
        Carbon $end,
        ?Carbon $lastResetAt = null,
        ?string $discordChannelId = null,
    ): Event {
        return Event::factory()->create([
            'start_datetime'              => $start,
            'end_datetime'                => $end,
            'recurrence_rule'             => 'FREQ=WEEKLY;BYDAY=SA',
            'discord_staffing_channel_id' => $discordChannelId ?? (string) rand(100000000, 999999999),
            'last_staffing_reset_at'      => $lastResetAt,
        ]);
    }

    private function createBookedPosition(Event $event): StaffingPosition
    {
        $staffing = Staffing::factory()->create(['event_id' => $event->id]);

        return StaffingPosition::factory()->create([
            'staffing_id'               => $staffing->id,
            'booked_by_user_id'         => 1,
            'discord_user_id'           => '987654321',
            'vatsim_cid'                => '1234567',
            'control_center_booking_id' => 1,
        ]);
    }
}
