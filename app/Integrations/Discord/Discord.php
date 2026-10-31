<?php

namespace App\Integrations\Discord;

use App\Integrations\Configuration;
use App\Integrations\ConnectionFailed;
use App\Integrations\Integration;
use App\Integrations\Setting;

/**
 * The vACC's Discord server, posted to through channel webhooks only, with no bot user (see
 * ADR 0005). Discord shows what happens on the Hub; nothing is booked from it. Webhooks must
 * match WEBHOOK_PATTERN and are only called through Webhook, so they only ever reach Discord.
 */
class Discord extends Integration
{
    /** What a channel webhook's URL looks like, on either of Discord's domains. */
    public const string WEBHOOK_PATTERN = '#^https://(?:(?:ptb|canary)\.)?discord(?:app)?\.com/api/webhooks/\d+/[\w-]+$#';

    public function key(): string
    {
        return 'discord';
    }

    public function name(): string
    {
        return 'Discord';
    }

    public function description(): string
    {
        return 'Your vACC’s Discord server: published events are announced in its events channel, and each event’s roster is kept up to date in its own channel.';
    }

    public function settings(): array
    {
        return [
            new Setting(
                'events_webhook',
                'Events channel webhook',
                'DISCORD_EVENTS_WEBHOOK',
                ['string', 'max:255', 'regex:'.self::WEBHOOK_PATTERN],
                secret: true,
                required: false,
                description: 'A webhook on the channel events are announced in (Server Settings → Integrations → Webhooks → Copy Webhook URL).',
            ),
        ];
    }

    public function features(): array
    {
        return [new EventAnnouncements, new RosterMessages];
    }

    public function testConnection(Configuration $configuration): void
    {
        if ($configuration->hasFeature('announcements')) {
            $this->checkWebhook($configuration->get('events_webhook'), 'events channel');
        }
    }

    /**
     * Fetching a webhook needs no other credentials, and fails once it's been deleted.
     *
     * @throws ConnectionFailed
     */
    private function checkWebhook(mixed $webhook, string $channel): void
    {
        if (blank($webhook)) {
            throw new ConnectionFailed("Enter the {$channel} webhook first.");
        }

        try {
            (new Webhook((string) $webhook))->check();
        } catch (WebhookRefused) {
            throw new ConnectionFailed("Discord doesn’t know the {$channel} webhook. It may have been deleted; copy a new one.");
        }
    }
}
