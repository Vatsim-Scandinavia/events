<?php

namespace App\Clients;

use Illuminate\Support\Facades\Http;

class DiscordClient
{
    protected ?string $webhookUrl;
    protected ?string $botApiUrl;
    protected ?string $botApiToken;
    protected ?string $botToken;
    protected ?string $guildId;
    protected string $baseUrl;

    public function __construct() {
        $this->webhookUrl = config('services.discord.webhook_url');
        $this->botApiUrl = config('services.discord.bot_api_url');
        $this->botApiToken = config('services.discord.bot_api_token');
        $this->botToken = config('services.discord.bot_token');
        $this->guildId = config('services.discord.guild_id');
        $this->baseUrl = 'https://discord.com/api/v10';
    }

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