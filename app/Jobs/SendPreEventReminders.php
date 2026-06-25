<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\DiscordNotificationService;
use App\Services\RecurringEventService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendPreEventReminders implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(
        RecurringEventService $recurringService,
        DiscordNotificationService $discordService
    ): void {
        Log::info('Starting pre-event reminder checks');

        // Get the time window: 2 hours from now (with 3 minute buffer)
        $targetTime = now()->addHours(2);
        $bufferStart = $targetTime->copy()->subMinutes(3);
        $bufferEnd = $targetTime->copy()->addMinutes(3);

        // Get events that may have an occurrence in the reminder window.
        $events = Event::where(function ($query) use ($bufferStart, $bufferEnd) {
            $query->whereNull('recurrence_rule')
                ->whereBetween('start_datetime', [$bufferStart, $bufferEnd]);
        })
            ->orWhere(function ($query) use ($bufferEnd) {
                $query->whereNotNull('recurrence_rule')
                    ->where('start_datetime', '<=', $bufferEnd);
            })
            ->get();

        foreach ($events as $event) {
            try {
                if ($event->isRecurring()) {
                    $this->handleRecurringEvent($event, $bufferStart, $bufferEnd, $recurringService, $discordService);
                } else {
                    $this->handleOneTimeEvent($event, $bufferStart, $bufferEnd, $discordService);
                }
            } catch (\Exception $e) {
                Log::error('Failed to process event for pre-event reminder', [
                    'event_id' => $event->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Completed pre-event reminder checks');
    }

    /**
     * Handle one-time event
     */
    private function handleOneTimeEvent(
        Event $event,
        Carbon $bufferStart,
        Carbon $bufferEnd,
        DiscordNotificationService $discordService
    ): void {
        // Check if event starts in the 2-hour window
        if ($event->start_datetime->between($bufferStart, $bufferEnd)) {
            // Check if we've already notified (using event date as key)
            $notifiedOccurrences = $event->notified_occurrences ?? [];
            $occurrenceKey = $this->occurrenceKey($event->start_datetime);

            if (! $this->hasNotifiedOccurrence($notifiedOccurrences, $event->start_datetime)) {
                // Send reminder
                if ($discordService->sendPreEventReminder($event, $event->start_datetime)) {
                    // Mark as notified
                    $notifiedOccurrences[] = $occurrenceKey;
                    $event->update(['notified_occurrences' => $notifiedOccurrences]);

                    Log::info('Sent pre-event reminder for one-time event', [
                        'event_id' => $event->id,
                        'event_title' => $event->title,
                        'start_time' => $event->start_datetime->toDateTimeString(),
                    ]);
                }
            }
        }
    }

    /**
     * Handle recurring event
     */
    private function handleRecurringEvent(
        Event $event,
        Carbon $bufferStart,
        Carbon $bufferEnd,
        RecurringEventService $recurringService,
        DiscordNotificationService $discordService
    ): void {
        // Generate upcoming instances
        $instances = $recurringService->generateInstances(
            $event->recurrence_rule,
            $event->start_datetime,
            $bufferEnd,
            50,
            $event->cancelled_occurrences ?? [],
            $bufferStart
        );

        foreach ($instances as $instance) {
            $occurrenceStart = $instance['start'];

            // Check if this occurrence starts in the 2-hour window
            if ($occurrenceStart->between($bufferStart, $bufferEnd)) {
                // Check if we've already notified for this occurrence
                $notifiedOccurrences = $event->notified_occurrences ?? [];
                $occurrenceKey = $this->occurrenceKey($occurrenceStart);

                if (! $this->hasNotifiedOccurrence($notifiedOccurrences, $occurrenceStart)) {
                    // Send reminder
                    if ($discordService->sendPreEventReminder($event, $occurrenceStart)) {
                        // Mark this occurrence as notified
                        $notifiedOccurrences[] = $occurrenceKey;
                        $event->update(['notified_occurrences' => $notifiedOccurrences]);

                        Log::info('Sent pre-event reminder for recurring event occurrence', [
                            'event_id' => $event->id,
                            'event_title' => $event->title,
                            'occurrence_start' => $occurrenceStart->toIso8601String(),
                        ]);
                    }
                }
            }
        }
    }

    private function occurrenceKey(Carbon $occurrenceStart): string
    {
        return $occurrenceStart->copy()->utc()->toISOString();
    }

    private function hasNotifiedOccurrence(array $notifiedOccurrences, Carbon $occurrenceStart): bool
    {
        foreach ($notifiedOccurrences as $notifiedOccurrence) {
            try {
                if (Carbon::parse($notifiedOccurrence)->equalTo($occurrenceStart)) {
                    return true;
                }
            } catch (\Exception) {
                continue;
            }
        }

        return false;
    }
}
