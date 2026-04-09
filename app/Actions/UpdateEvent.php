<?php

namespace App\Actions;

use App\Actions\Concerns\ValidatesRecurrenceRule;
use App\Models\Event;
use App\Models\User;
use App\Services\BannerUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class UpdateEvent
{
    use ValidatesRecurrenceRule;
    public function __construct(protected BannerUploadService $bannerUploadService) {}

    /**
     * Execute the action.
     * 
     * @param Event $event The event to update
     * @param array $data The updated event data
     * @param User $user The user performing the update
     * @param UploadedFile|null $banner The banner image file
     * @return Event
     */
    public function __invoke(Event $event, array $data, User $user, ?UploadedFile $banner = null): Event
    {
        if (!empty($data['recurrence_rule'])) {
            $this->validateRecurrenceRule($data['recurrence_rule']);
        }

        return DB::transaction(function () use ($event, $data, $user, $banner) {

            if ($banner instanceof UploadedFile) {
                $oldBanner = $event->banner_path;
                $event->banner_path = $this->bannerUploadService->upload($banner);
                if ($oldBanner) {
                    Storage::disk('public')->delete($oldBanner);
                }
            }

            $event->update($data);

            Log::info(
                "Event updated by " . ($user->vatsim_cid ?? 'System') . ": Event ID {$event->id}, Title: {$event->title}"
            );

            return $event;
        });
    }
}
