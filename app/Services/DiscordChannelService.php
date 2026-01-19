<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordChannelService
{
    protected string $botToken;
    protected ?string $guildId;
    protected string $baseUrl = 'https://discord.com/api/v10';

    public function __construct()
    {
        $this->botToken = config('services.discord.bot_token');
        $this->guildId = config('services.discord.guild_id');
    }

    /**
     * Get available Discord guilds (servers) and their channels
     */
    public function getAvailableChannels(): array
    {
        if (empty($this->botToken)) {
            Log::warning('Discord bot token not configured');
            return [];
        }

        try {
            // If a specific guild ID is configured, only fetch that guild's channels
            if (!empty($this->guildId)) {
                return $this->getChannelsForGuild($this->guildId);
            }

            // Otherwise, get all guilds the bot is in
            $guildsResponse = Http::withHeaders([
                'Authorization' => 'Bot ' . $this->botToken,
            ])->get("{$this->baseUrl}/users/@me/guilds");

            if (!$guildsResponse->successful()) {
                Log::error('Failed to fetch Discord guilds', [
                    'status' => $guildsResponse->status(),
                    'body' => $guildsResponse->body(),
                ]);
                return [];
            }

            $guilds = $guildsResponse->json();
            $result = [];

            foreach ($guilds as $guild) {
                $guildChannels = $this->getChannelsForGuild($guild['id'], $guild['name']);
                if (!empty($guildChannels)) {
                    $result = array_merge($result, $guildChannels);
                }
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Discord get channels exception', [
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get channels for a specific guild
     */
    protected function getChannelsForGuild(string $guildId, ?string $guildName = null): array
    {
        try {
            // If guild name is not provided, fetch it
            if ($guildName === null) {
                $guildResponse = Http::withHeaders([
                    'Authorization' => 'Bot ' . $this->botToken,
                ])->get("{$this->baseUrl}/guilds/{$guildId}");

                if ($guildResponse->successful()) {
                    $guild = $guildResponse->json();
                    $guildName = $guild['name'];
                } else {
                    $guildName = 'Unknown Server';
                }
            }

            // Get channels for the guild
            $channelsResponse = Http::withHeaders([
                'Authorization' => 'Bot ' . $this->botToken,
            ])->get("{$this->baseUrl}/guilds/{$guildId}/channels");

            if (!$channelsResponse->successful()) {
                Log::error('Failed to fetch Discord channels for guild', [
                    'guild_id' => $guildId,
                    'status' => $channelsResponse->status(),
                ]);
                return [];
            }

            $channels = $channelsResponse->json();
            
            // Filter for text channels only (type 0 = text channel)
            // AND channels that contain "staffing" in their name (case-insensitive)
            $textChannels = array_filter($channels, function($channel) {
                return $channel['type'] === 0 && 
                       (stripos($channel['name'], 'staffing') !== false || 
                        stripos($channel['name'], 'signup') !== false);
            });

            if (empty($textChannels)) {
                return [];
            }

            return [[
                'guild_id' => $guildId,
                'guild_name' => $guildName,
                'channels' => array_map(function($channel) {
                    return [
                        'id' => $channel['id'],
                        'name' => $channel['name'],
                    ];
                }, array_values($textChannels)),
            ]];
        } catch (\Exception $e) {
            Log::error('Discord get channels for guild exception', [
                'guild_id' => $guildId,
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Test bot connection
     */
    public function testConnection(): bool
    {
        if (empty($this->botToken)) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bot ' . $this->botToken,
            ])->get("{$this->baseUrl}/users/@me");

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
