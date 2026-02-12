<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordNotificationService
{
    /**
     * Send event notification to Discord
     */
    public function sendEventNotification(Event $event, string $action = 'created'): bool
    {
        $webhookUrl = config('services.discord.webhook_url');

        if (empty($webhookUrl)) {
            Log::warning('Discord webhook URL not configured');
            return false;
        }

        try {
            $embed = $this->buildEventEmbed($event, $action);

            $response = Http::timeout(10)
                ->post($webhookUrl, [
                    'content' => $this->getNotificationMessage($action),
                    'embeds' => [$embed],
                ]);

            if ($response->successful()) {
                Log::info('Discord notification sent successfully', [
                    'event_id' => $event->id,
                    'action' => $action,
                ]);
                return true;
            }

            Log::error('Discord webhook request failed', [
                'event_id' => $event->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Discord notification exception', [
                'event_id' => $event->id,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Build Discord embed for an event
     */
    protected function buildEventEmbed(Event $event, string $action): array
    {
        $color = match($action) {
            'created' => 0x41826e, // Success green
            'updated' => 0xff9800, // Warning orange
            'deleted' => 0xb63f3f, // Danger red
            default => 0x3498db,   // Primary blue
        };

        // Build the description with formatted date
        $startDate = $event->start_datetime->format('d M Y');
        $startTime = $event->start_datetime->format('H:i');
        $endTime = $event->end_datetime->format('H:i');
        
        $description = "{$event->short_description}\n\n";

        $embed = [
            'title' => $event->title,
            'description' => $description,
            'color' => $color,
            'url' => url('/events/' . $event->id),
            'footer' => [
                'text' => 'Starting time • ' . $event->start_datetime->format('d/m/Y'),
            ],
        ];

        // Add banner image if available
        if ($event->banner_path) {
            $bannerUrl = $this->getBannerUrl($event->banner_path);
            if ($bannerUrl) {
                $embed['image'] = ['url' => $bannerUrl];
            }
        }

        return $embed;
    }

    /**
     * Get notification message based on action
     */
    protected function getNotificationMessage(string $action): string
    {
        $roleId = config('services.discord.mention_role_id');
        $roleMention = $roleId ? "<@&{$roleId}> " : '';
        
        return match($action) {
            'created' => 'A new event has been scheduled.',
            'updated' => '✏️ An event has been updated.',
            'deleted' => '🗑️ An event has been deleted.',
            default => '📢 Event notification',
        };
    }

    /**
     * Get banner URL
     */
    protected function getBannerUrl(string $bannerPath): ?string
    {
        $disk = config('filesystems.default');

        if ($disk === 's3') {
            return \Storage::disk($disk)->url($bannerPath);
        }

        // For local storage, return full absolute URL
        // Make sure APP_URL is set correctly in .env for Discord to access the image
        return config('app.url') . '/storage/' . $bannerPath;
    }

    /**
     * Test Discord webhook connection
     */
    public function testConnection(): bool
    {
        $webhookUrl = config('services.discord.webhook_url');

        if (empty($webhookUrl)) {
            return false;
        }

        try {
            $response = Http::timeout(10)
                ->post($webhookUrl, [
                    'content' => '✅ Discord webhook test successful!',
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Send 2-hour pre-event reminder
     */
    public function sendPreEventReminder(Event $event, \Carbon\Carbon $occurrenceStart): bool
    {
        $webhookUrl = config('services.discord.webhook_url');

        if (empty($webhookUrl)) {
            Log::warning('Discord webhook URL not configured for pre-event reminder');
            return false;
        }

        try {
            $roleId = config('services.discord.mention_role_id');
            $roleMention = $roleId ? "<@&{$roleId}> " : '@everyone ';

            $embed = [
                'title' => "⏰ Event Starting Soon: {$event->title}",
                'description' => "{$event->short_description}\n\n🕐 **Starting in 2 hours!**",
                'color' => 0xff9800, // Orange for reminder
                'url' => url('/events/' . $event->id),
                'timestamp' => $occurrenceStart->toIso8601String(),
                'footer' => [
                    'text' => 'Event starts at',
                ],
            ];

            // Add banner if available
            if ($event->banner_path) {
                $embed['image'] = [
                    'url' => $this->getBannerUrl($event->banner_path),
                ];
            }

            $response = Http::timeout(10)
                ->post($webhookUrl, [
                    'content' => $roleMention . 'An event is starting in 2 hours!',
                    'embeds' => [$embed],
                ]);

            if ($response->successful()) {
                Log::info('Pre-event reminder sent successfully', [
                    'event_id' => $event->id,
                    'occurrence_start' => $occurrenceStart->toDateTimeString(),
                ]);
                return true;
            }

            Log::error('Pre-event reminder webhook request failed', [
                'event_id' => $event->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Pre-event reminder exception', [
                'event_id' => $event->id,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
