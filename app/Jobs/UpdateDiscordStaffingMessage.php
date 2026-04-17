<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\DiscordBotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class UpdateDiscordStaffingMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public readonly int $eventId,
        public readonly string $action = 'update',
        public readonly bool $reset = false,
    ) {}

    public function handle(DiscordBotService $discordBotService): void
    {
        $event = Event::with('staffing')->find($this->eventId);

        if (! $event) {
            Log::warning('UpdateDiscordStaffingMessage: event not found', ['event_id' => $this->eventId]);
            return;
        }

        $discordBotService->dispatchStaffingUpdate($event, $this->action, $this->reset);
    }
}
