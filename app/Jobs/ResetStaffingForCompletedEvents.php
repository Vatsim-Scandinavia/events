<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\EventService;
use App\Services\ControlCenterService;
use App\Services\DiscordBotNotificationService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ResetStaffingForCompletedEvents implements ShouldQueue
{
    use Queueable;

    private const MAX_HOURS_AFTER_END = 48;

    public function handle(
        EventService $eventService,
        ControlCenterService $controlCenterService,
        DiscordBotNotificationService $discordBotService
    ): void {
        Log::info('Staffing Reset: Checking for completed occurrences...');

        $recurringEvents = Event::whereNotNull('recurrence_rule')
            ->whereNotNull('discord_staffing_channel_id')
            ->get();

        foreach ($recurringEvents as $event) {
            try {
                $this->processEvent($event, $eventService, $controlCenterService, $discordBotService);
            } catch (\Exception $e) {
                Log::error("Staffing Reset: Failed for Event #{$event->id} '{$event->title}': " . $e->getMessage());
            }
        }

        Log::info('Staffing Reset: Check complete.');
    }

    private function processEvent(
        Event $event,
        EventService $eventService,
        ControlCenterService $controlCenterService,
        DiscordBotNotificationService $discordBotService
    ): void {
        $lastOcc = $eventService->getLastEndedOccurrence($event);

        if (!$lastOcc) {
            Log::debug("Staffing Reset: No ended occurrence found for '{$event->title}', skipping.");
            return;
        }

        $endedAt = Carbon::parse($lastOcc['end']);

        if ($endedAt->isFuture()) {
            Log::debug("Staffing Reset: Last occurrence for '{$event->title}' hasn't ended yet, skipping.");
            return;
        }

        $hoursSinceEnd = $endedAt->diffInHours(now());

        if ($hoursSinceEnd > self::MAX_HOURS_AFTER_END) {
            Log::debug("Staffing Reset: '{$event->title}' ended {$hoursSinceEnd}h ago, outside reset window.");
            return;
        }

        $alreadyReset = $event->last_staffing_reset_at &&
                        $event->last_staffing_reset_at->greaterThanOrEqualTo($endedAt);

        if ($alreadyReset) {
            Log::debug("Staffing Reset: '{$event->title}' already reset for occurrence ending at {$endedAt}.");
            return;
        }

        Log::info("Staffing Reset: Resetting '{$event->title}' (occurrence ended at {$endedAt}, {$hoursSinceEnd}h ago).");

        $this->performReset($event, $controlCenterService, $discordBotService);

        $event->update(['last_staffing_reset_at' => $endedAt]);

        Log::info("Staffing Reset: Successfully completed for '{$event->title}'.");
    }

    private function performReset(
        Event $event,
        ControlCenterService $controlCenter,
        DiscordBotNotificationService $discordBot
    ): void {
        // Collect Control Center booking IDs before touching the DB so we know
        // exactly what to clean up externally, regardless of what happens later.
        $controlCenterBookingIds = $event->staffings()
            ->with('positions')
            ->get()
            ->flatMap(fn($staffing) => $staffing->positions)
            ->filter(fn($position) => $position->isBooked() && $position->control_center_booking_id)
            ->pluck('control_center_booking_id')
            ->all();

        // Clear all booking fields in a single, focused DB transaction with no
        // external side-effects so the transaction stays short and fully rollbackable.
        DB::transaction(function () use ($event) {
            $event->staffings()->with('positions')->get()
                ->each(function ($staffing) {
                    $staffing->positions->each(fn($position) => $position->update([
                        'booked_by_user_id'        => null,
                        'discord_user_id'           => null,
                        'vatsim_cid'                => null,
                        'control_center_booking_id' => null,
                    ]));
                });
        });

        // External calls happen after the DB transaction has committed so that:
        // 1. A failed network call cannot cause a DB rollback that leaves data inconsistent.
        // 2. The transaction is not held open while waiting on slow HTTP responses.
        foreach ($controlCenterBookingIds as $bookingId) {
            try {
                $controlCenter->deleteBooking($bookingId);
            } catch (\Exception $e) {
                Log::warning("Staffing Reset: Failed to delete CC booking #{$bookingId}: " . $e->getMessage());
            }
        }

        try {
            $discordBot->notifyStaffingChanged($event, 'reset');
        } catch (\Exception $e) {
            Log::warning("Staffing Reset: Failed to notify Discord for Event #{$event->id}: " . $e->getMessage());
        }
    }
}