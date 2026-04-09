<?php

namespace App\Actions;

use App\Actions\Concerns\ValidatesRecurrenceRule;
use App\Models\Event;
use App\Models\User;
use App\Services\BannerUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class CreateEvent
{
    use ValidatesRecurrenceRule;
    public function __construct(protected BannerUploadService $bannerUploadService) {}

    /**
     * Execute the action.
     * 
     * @param array $data The event data
     * @param User $user The user creating the event
     * @return Event
     */
    public function __invoke(array $data, User $user): Event
    {
        if (!empty($data['recurrence_rule'])) {
            $this->validateRecurrenceRule($data['recurrence_rule']);
        }

        $banner = $data['banner'] ?? null;
        unset($data['banner']);

        return DB::transaction(function () use ($data, $user, $banner) {

            $bannerPath = $banner instanceof UploadedFile
                ? $this->bannerUploadService->upload($banner)
                : null;

            $event = Event::create([
                ...$data,
                'banner_path' => $bannerPath,
                'created_by' => $user->id,
            ]);

            Log::info(
                "Event created by " . ($user->vatsim_cid ?? 'System') . ": Event ID {$event->id}, Title: {$event->title}"
            );

            return $event;
        });
    }
}
