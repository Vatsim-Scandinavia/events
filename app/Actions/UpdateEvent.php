<?php

namespace App\Actions;

use App\Models\Event;
use App\Models\Staffing;
use App\Models\User;
use App\Services\BannerService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class UpdateEvent
{
    public function __construct(
        protected BannerService $bannerService,
        protected ShiftEventOccurrences $shiftEventOccurrences,
    ) {}

    public function __invoke(Event $event, array $data, User $user, Carbon $start, Carbon $end, ?UploadedFile $banner = null): Event
    {
        return DB::transaction(function () use ($event, $data, $user, $start, $end, $banner) {
            // Pull discord_channel_id out before updating event fields.
            $discordChannelId = array_key_exists('discord_channel_id', $data)
                ? $data['discord_channel_id']
                : false;
            unset($data['discord_channel_id']);

            $this->updateOccurrences($event, $start, $end);
            $this->replaceBanner($event, $banner);

            $event->update($data);

            if ($discordChannelId !== false) {
                $this->syncStaffingChannel($event, $discordChannelId);
            }

            Log::info("Event updated by {$user->id}: Event ID {$event->id}, Title: {$event->title}");

            return $event;
        });
    }

    /**
     * Move the representative occurrence to the new start/end times and,
     * for recurring events, shift all remaining future occurrences by the
     * same offset so the series stays in sync.
     */
    private function updateOccurrences(Event $event, Carbon $start, Carbon $end): void
    {
        $occurrence = $event->occurrences()
            ->where('start_time', '>=', now())
            ->orderBy('start_time')
            ->first()
            ?? $event->occurrences()->orderByDesc('start_time')->first();

        if (! $occurrence) {
            return;
        }

        $shiftSeconds    = Carbon::parse($occurrence->start_time, 'UTC')->diffInSeconds($start, false);
        $durationSeconds = $start->diffInSeconds($end, false);

        $occurrence->update(['start_time' => $start, 'end_time' => $end]);

        if ($shiftSeconds !== 0 && $event->recurrence_rule) {
            ($this->shiftEventOccurrences)($event, $occurrence, $shiftSeconds, $durationSeconds);
        }
    }

    /**
     * Replace the event banner when a new file is provided,
     * deleting the previous file from storage.
     */
    private function replaceBanner(Event $event, ?UploadedFile $banner): void
    {
        if (! $banner) {
            return;
        }

        $oldPath = $event->banner_path;
        $event->banner_path = $this->bannerService->upload($banner);

        if ($oldPath) {
            $this->bannerService->delete($oldPath);
        }
    }

    /**
     * Upsert the Staffing record's Discord channel binding for this event.
     *
     * @param string|null $channelId Pass null to clear the channel.
     */
    private function syncStaffingChannel(Event $event, ?string $channelId): void
    {
        Staffing::updateOrCreate(
            ['event_id' => $event->id],
            ['discord_channel_id' => $channelId ?: null]
        );
    }
}
