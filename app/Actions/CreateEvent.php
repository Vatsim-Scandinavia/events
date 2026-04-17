<?php

namespace App\Actions;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\Staffing;
use App\Models\User;
use App\Services\BannerService;
use App\Services\RecurrenceService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class CreateEvent
{
    public function __construct(
        protected BannerService $bannerService,
        protected RecurrenceService $recurrenceService
    ) {}

    /**
     * Execute the action.
     * 
     * @param array $data The event data
     * @param User $user The user creating the event
     * @param UploadedFile|null $banner The banner image file
     * @return Event
     */
    public function __invoke(array $data, User $user, Carbon $start, Carbon $end, ?UploadedFile $banner = null): Event
    {
        return DB::transaction(function () use ($data, $user, $start, $end, $banner) {
            $discordChannelId = $data['discord_channel_id'] ?? null;
            unset($data['discord_channel_id']);

            $baseSlug = Str::slug($data['title']);
            $slug = $baseSlug;
            $i = 1;
            while (Event::where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$i}";
                $i++;
            }

            $event = Event::create([
                ...$data,
                'slug' => $slug,
                'banner_path' => $banner ? $this->bannerService->upload($banner) : null,
                'created_by' => $user->id,
            ]);

            EventOccurrence::create([
                'event_id'   => $event->id,
                'start_time' => $start,
                'end_time'   => $end,
                'status'     => 'scheduled',
            ]);

            if ($event->recurrence_rule) {
                $this->recurrenceService->generate($event);
            }

            if ($discordChannelId) {
                Staffing::create([
                    'event_id'          => $event->id,
                    'discord_channel_id' => $discordChannelId,
                ]);
            }

            Log::info("Event created by {$user->id}: Event ID {$event->id}, Title: {$event->title}");

            return $event;
        });
    }
}
