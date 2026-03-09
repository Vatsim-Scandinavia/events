<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordBotNotificationService
{
    protected ?string $botWebhookUrl;
    protected ?string $botApiToken;
    protected ?bool $shouldReset = false;

    public function __construct()
    {
        $this->botWebhookUrl = config('services.discord.bot_api_url');
        $this->botApiToken = config('services.discord.bot_api_token');
    }

    /**
     * Notify Discord bot about staffing changes
     */
    public function notifyStaffingChanged(Event $event, string $action = 'updated'): bool
    {
        if (empty($this->botWebhookUrl)) {
            Log::info('Discord bot API URL not configured, skipping notification');
            return false;
        }

        // Only notify if event has a Discord channel configured
        if (!$event->discord_staffing_channel_id) {
            return false;
        }

        if ($action === 'reset') {
            $this->shouldReset = true;
        }

        try {
            $event->load(['calendar', 'staffings.positions.bookedBy']);

            $response = Http::timeout(10) // Increased timeout to 10 seconds
                ->withToken($this->botApiToken)
                ->asForm()
                ->post($this->botWebhookUrl . '/staffings/' . ($action === 'setup' ? 'setup' : 'update'), [
                    'id' => $event->staffings->first()->id ?? null,
                    'reset' => $this->shouldReset,
                ]);

            if ($response->successful()) {
                Log::info('Discord bot notified successfully', [
                    'event_id' => $event->id,
                    'action' => $action,
                ]);
                return true;
            }

            Log::warning('Discord bot notification failed', [
                'event_id' => $event->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Discord bot notification exception', [
                'event_id' => $event->id,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
