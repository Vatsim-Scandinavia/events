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

    public function handle(
        EventService $eventService,
        ControlCenterService $controlCenterService,
        DiscordBotNotificationService $discordBotService
    ): void {
        Log::info('Staffing Reset: Checking for completed occurrences...');

        $recurringEvents = Event::whereNotNull('recurrence_rule')->get();

        foreach ($recurringEvents as $event) {
            try {
                $lastOcc = $eventService->getLastEndedOccurrence($event);
                if (!$lastOcc) continue;

                $endedAt = Carbon::parse($lastOcc['end']);

                // Reset criteria: Ended within 48h and not reset since that end time
                $alreadyReset = $event->last_staffing_reset_at && 
                                $event->last_staffing_reset_at->greaterThanOrEqualTo($endedAt);

                if ($endedAt->diffInHours(now()) <= 48 && !$alreadyReset) {
                    $this->performReset($event, $controlCenterService, $discordBotService);
                    
                    $event->update(['last_staffing_reset_at' => now()]);
                    Log::info("Staffing Reset: Completed for '{$event->title}'");
                }
            } catch (\Exception $e) {
                Log::error("Staffing Reset: Failed for Event #{$event->id}: " . $e->getMessage());
            }
        }
    }

    private function performReset(Event $event, $controlCenter, $discordBot): void
    {
        DB::transaction(function () use ($event, $controlCenter, $discordBot) {
            $staffings = $event->staffings()->with('positions')->get();

            foreach ($staffings as $staffing) {
                foreach ($staffing->positions as $position) {
                    if ($position->control_center_booking_id) {
                        try {
                            $controlCenter->deleteBooking($position->control_center_booking_id);
                        } catch (\Exception $e) {
                            Log::warning("CC Delete error: " . $e->getMessage());
                        }
                    }

                    $position->update([
                        'booked_by_user_id' => null,
                        'discord_user_id' => null,
                        'vatsim_cid' => null,
                        'control_center_booking_id' => null,
                    ]);
                }
            }

            $discordBot->notifyStaffingChanged($event, 'updated');
        });
    }
}