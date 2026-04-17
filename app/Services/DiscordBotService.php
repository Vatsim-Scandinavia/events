<?php

namespace App\Services;

use App\Clients\DiscordClient;
use App\Models\Event;

class DiscordBotService
{
    protected string $guildId;

    public function __construct(private DiscordClient $client)
    {
        $this->guildId = config('services.discord.guild_id');
    }

    /**
     * Dispatch a staffing update to the Discord bot for a given event
     * @param Event $event
     * @param string $action - 'update' or 'setup'
     * @param bool $reset - whether this is a reset action (clearing all staffings)
     * @return bool
     */
    public function dispatchStaffingUpdate(Event $event, string $action = 'update', bool $reset = false): bool
    {
        if (!$event->staffing?->discord_channel_id) return false;

        $payload = [
            'id' => $event->staffing?->id ?? null,
            'reset' => $reset,
        ];

        $path = '/staffings/' . ($action === 'setup' ? 'setup' : 'update');

        return $this->client->sendBot($payload, $path);
    }

    /**
     * Get channels from the configured guild/server, optionally filtering by name
     * @param array $nameFilters - array of strings to filter channel names by (case-insensitive, partial match)
     * @return array
     */
    public function getGuildChannels(array $nameFilters = []): array
    {
        if (!$this->guildId) return [];

        $channels = $this->client->get("/guilds/{$this->guildId}/channels") ?? [];

        if (empty($nameFilters)) {
            return $channels;
        }

        $nameFilters = array_map('strtolower', $nameFilters);

        return array_filter($channels, function ($channel) use ($nameFilters) {
            if (!isset($channel['name'])) return false;

            $channelName = strtolower($channel['name']);

            foreach ($nameFilters as $filter) {
                if (strpos($channelName, $filter) !== false) {
                    return true;
                }
            }

            return false;
        });
    }
}
