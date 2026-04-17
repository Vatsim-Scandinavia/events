<?php

namespace App\Actions;

use App\Models\Event;
use App\Models\User;
use App\Services\BannerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class DeleteEvent
{
    public function __construct(protected BannerService $bannerService) {}

    /**
     * Execute the action.
     * 
     * @param Event $event The event to delete
     * @param User $user The user performing the deletion
     * @return void
     */
    public function __invoke(Event $event, User $user): void
    {
        DB::transaction(function () use ($event, $user) {
            if ($event->banner_path) {
                $this->bannerService->delete($event->banner_path);
            }

            $event->delete();

            Log::info("Event deleted by {$user->id}: Event ID {$event->id}, Title: {$event->title}");
        });
    }
}
