<?php

namespace App\Actions;

use App\Models\Event;
use App\Models\User;
use App\Services\BannerUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Recurr\Rule;

final class UpdateEvent
{
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

            $event->banner_path = $banner instanceof UploadedFile
                ? $this->bannerUploadService->upload($banner)
                : $event->banner_path;

            $event->update($data);

            Log::info(
                "Event updated by " . ($user->vatsim_cid ?? 'System') . ": Event ID {$event->id}, Title: {$event->title}"
            );

            return $event;
        });
    }

    protected function validateRecurrenceRule(string $rule): void
    {
        try {
            new Rule($rule);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'recurrence_rule' => ['The recurrence rule is invalid: ' . $e->getMessage()],
            ]);
        }
    }
}
