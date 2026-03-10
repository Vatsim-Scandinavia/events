<?php

namespace App\Services;

use App\Clients\DiscordClient;

class DiscordService
{
    public function __construct(private DiscordClient $client) {}

    /**
     * Send a message to the configured Discord webhook
     * 
     * @param string $message
     * @param array|null $embed
     * @return bool
     */
    public function send(string $message, array $embed = []): bool
    {
        return $this->dispatchWebhookNotification($message, $embed);
    }

    /**
     * Send a notification to the configured Discord webhook
     * 
     * @param string $message
     * @param array|null $embed
     * @return bool
     */
    public function dispatchWebhookNotification(string $message, array $embed = []): bool
    {
        $payload = [
            'content' => $message,
            'embeds' => $embed ? [$embed] : [],
        ];

        return $this->client->sendWebhook($payload);
    }
}
