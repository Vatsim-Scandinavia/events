<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\StaffingPosition;
use App\Services\EventService;
use App\Services\ControlCenterService;
use App\Services\DiscordBotService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ResetStaffingForCompletedEvents implements ShouldQueue
{
    use Queueable;

    private const MAX_HOURS_AFTER_END = 48;

    public function handle(EventService $eventService, ControlCenterService $controlCenterService, DiscordBotService $discordBotService): void
    {
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
    }

    private function processEvent(Event $event, EventService $eventService, ControlCenterService $controlCenterService, DiscordBotService $discordBotService): void
    {
        $lastOcc = $eventService->getLastEndedOccurrence($event);

        if (!$lastOcc) return;

        $endedAt = Carbon::parse($lastOcc['end']);

        if ($endedAt->isFuture()) return;

        $hoursSinceEnd = $endedAt->diffInHours(now());

        if ($hoursSinceEnd > self::MAX_HOURS_AFTER_END) return;

        $alreadyReset = $event->last_staffing_reset_at &&
            $event->last_staffing_reset_at->greaterThanOrEqualTo($endedAt);

        if ($alreadyReset) return;

        $this->performReset($event, $controlCenterService, $discordBotService);

        $event->update(['last_staffing_reset_at' => $endedAt]);
    }

    private function performReset(
        Event $event,
        ControlCenterService $controlCenter,
        DiscordBotService $discordBot
    ): void {
        $staffings = $event->staffings()->with('positions')->get();
        $positions = $staffings->flatMap(fn($staffing) => $staffing->positions);

        $controlCenterBookingIds = $positions
            ->filter(fn($position) => $position->isBooked() && $position->control_center_booking_id)
            ->pluck('control_center_booking_id')
            ->all();

        DB::transaction(function () use ($positions) {
            StaffingPosition::whereIn('id', $positions->pluck('id'))->update([
                'booked_by_user_id'        => null,
                'discord_user_id'          => null,
                'vatsim_cid'               => null,
                'control_center_booking_id' => null,
            ]);
        });

        foreach ($controlCenterBookingIds as $bookingId) {
            try {
                $controlCenter->deleteBooking($bookingId);
            } catch (\Exception $e) {
                Log::warning("Staffing Reset: Failed to delete CC booking #{$bookingId}: " . $e->getMessage());
            }
        }

        try {
            $discordBot->dispatchStaffingUpdate($event, reset: true);
        } catch (\Exception $e) {
            Log::warning("Staffing Reset: Failed to notify Discord for Event #{$event->id}: " . $e->getMessage());
        }
    }
}
