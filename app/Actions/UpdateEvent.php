<?php

namespace App\Actions;

use App\Actions\Concerns\ValidatesRecurrenceRule;
use App\Models\Event;
use App\Models\User;
use App\Services\BannerUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
     * @return Event
     */
    public function __invoke(Event $event, array $data, User $user): Event
    {
        if (!empty($data['recurrence_rule'])) {
            $this->validateRecurrenceRule($data['recurrence_rule']);
        }

        $banner = $data['banner'] ?? null;
        unset($data['banner']);

        return DB::transaction(function () use ($event, $data, $user, $banner) {

            if ($banner instanceof UploadedFile) {
                if ($event->banner_path) {
                    Storage::disk('public')->delete($event->banner_path);
                }
                $event->banner_path = $this->bannerUploadService->upload($banner);
            }

            $event->update($data);

            Log::info(
                "Event updated by " . ($user->vatsim_cid ?? 'System') . ": Event ID {$event->id}, Title: {$event->title}"
            );

            return $event;
        });
    }
}
