<?php

namespace App\Http\Controllers\Api;

use App\Clients\DiscordClient;
use App\Http\Controllers\Controller;

class DiscordController extends Controller
{
    public function getChannels()
    {
        $channels = app(DiscordClient::class)->get('/guilds/' . env('DISCORD_GUILD_ID') . '/channels');

        $filtered = array_values(array_map(
            fn($channel) => ['id' => $channel['id'], 'name' => $channel['name']],
            array_filter($channels ?? [], function ($channel) {
                return $channel['type'] === 0
                    && (stripos($channel['name'], 'staffing') !== false || stripos($channel['name'], 'signup') !== false);
            })
        ));

        return response()->json($filtered);
    }
}
