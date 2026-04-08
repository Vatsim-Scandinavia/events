<?php

namespace App\Clients;

use Illuminate\Support\Facades\Http;

class DiscordClient
{
    /**
     * The Discord webhook URL for sending messages to a channel.
     */
    protected ?string $webhookUrl;
    
    /**
     * The base URL for the Discord API.
     */
    protected ?string $botApiUrl;

    /**
     * The API token for the Discord bot.
     */
    protected ?string $botApiToken;

    /**
     * The bot token for authenticating with the Discord API.
     */
    protected ?string $botToken;

    /**
     * The ID of the guild (server) to which the bot belongs.
     */
    protected ?string $guildId;

    /**
     * The base URL for the Discord API.
     */
    protected string $baseUrl;

    public function __construct() {
        $this->webhookUrl = config('services.discord.webhook_url');
        $this->botApiUrl = config('services.discord.bot_api_url');
        $this->botApiToken = config('services.discord.bot_api_token');
        $this->botToken = config('services.discord.bot_token');
        $this->guildId = config('services.discord.guild_id');
        $this->baseUrl = 'https://discord.com/api/v10';
    }

    /**
     * Send a message to a Discord channel via webhook.
     * @param array $payload The payload to send to the webhook.
     * @return bool True if the message was sent successfully, false otherwise.
     * @throws \Throwable If an error occurs while sending the message.
     */
    public function sendWebhook(array $payload): bool
    {
        if (!$this->webhookUrl) return false;

        try {
            $response = Http::post($this->webhookUrl, $payload);

            return $response->successful();
        } catch (\Throwable $th) {
            report($th);
            return false;
        }
    }

    /**
     * Send a message to a Discord channel via bot API.
     * @param array $payload The payload to send to the bot API.
     * @return bool True if the message was sent successfully, false otherwise.
     * @throws \Throwable If an error occurs while sending the message.
     */
    public function sendBot(array $payload): bool
    {
        if (!$this->botApiUrl || !$this->botApiToken) return false;

        try {
            $response = Http::withToken($this->botApiToken)
                ->asForm()
                ->post($this->botApiUrl, $payload);

            return $response->successful();
        } catch (\Throwable $th) {
            report($th);
            return false;
        }
    }

    /**
     * Make a GET request to the Discord API.
     * @param string $endpoint The API endpoint to call (e.g., '/guilds/{guild.id}/channels').
     * @param array $headers Optional headers to include in the request.
     * @return array|null The response data as an associative array, or null if the request failed or if the bot token is not set.
     * @throws \Throwable If an error occurs while making the request.
     */
    public function get(string $endpoint, array $headers = []): ?array
    {
        if (!$this->botToken) return null;

        try {
            $response = Http::withHeaders(array_merge([
                'Authorization' => "Bot {$this->botToken}"
            ], $headers))->get($this->baseUrl . $endpoint);
            
            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Throwable $th) {
            report($th);
            return null;
        }
    }
}