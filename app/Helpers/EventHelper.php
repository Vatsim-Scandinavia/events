<?php

namespace App\Helpers;

use App\Models\DiscordMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

enum EventHelper: string
{
    case DAY = 'day';
    case WEEK = 'week';
    case MONTH = 'month';
    case YEAR = 'year';

    /**
     * Get the interval labels for the event types.
     */
    public static function labels(): array
    {
        return [
            self::DAY->value => 'Daily',
            self::WEEK->value => 'Weekly',
            self::MONTH->value => 'Monthly',
            self::YEAR->value => 'Yearly',
        ];
    }

    /**
     * Get code to mention a Discord role.
     */
    public static function discordMention(): string
    {
        if (config('discord.mention_role') === null) {
            return '';
        }

        return '<@&'.config('discord.mention_role').'>';
    }

    /**
     * Post a message to Discord.
     */
    public static function discordPost(string $text, string $title, string $content, ?string $image, Carbon $timestamp, ?Carbon $expireMessageAt = null): bool
    {
        $webhookUrl = config('discord.webhook');
        if ($webhookUrl === null || $webhookUrl == '') {
            return false;
        }

        $payload = [
            'content' => $text,
            'embeds' => [
                [
                    'title' => $title,
                    'url' => config('app.url'),
                    'description' => $content,
                    'image' => [
                        'url' => $image,
                    ],
                    'footer' => [
                        'text' => 'Starting time',
                        'icon_url' => 'https://cdnjs.cloudflare.com/ajax/libs/twemoji/15.1.0/72x72/1f551.png',
                    ],
                    'timestamp' => $timestamp->toIso8601String(),

                ],
            ],
        ];

        // Send the message to Discord
        $response = Http::post($webhookUrl.'?wait=true', $payload);
        $messageId = $response->json()['id'] ?? null;

        // Save the message for expiration
        if ($messageId !== null && $expireMessageAt !== null) {
            DiscordMessage::create([
                'message_id' => $messageId,
                'expires_at' => $expireMessageAt,
            ]);
        }

        return $messageId !== null;
    }

    /**
     * Delete a message from Discord.
     */
    public static function discordDelete(int $messageId): void
    {
        $webhookUrl = config('discord.webhook');
        if ($webhookUrl === null || $webhookUrl == '') {
            return;
        }

        Http::delete($webhookUrl."/messages/{$messageId}");
    }
}
