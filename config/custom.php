<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Custom Fields
    |--------------------------------------------------------------------------
    |
    */
    'cc_url' => env('CC_URL', null),
    'cc_api_secret' => env('CC_API_SECRET', null),

    'forum_api_url' => env('FORUM_API_URL', null),
    'forum_calendar_type' => env('FORUM_CALENDAR_TYPE', null),
    'forum_api_secret' => env('FORUM_API_SECRET', null),

    'discord_bot_token' => env('DISCORD_BOT_TOKEN', null),
    'discord_guild_id' => env('DISCORD_GUILD_ID', null),

    'events_api_key' => env('EVENTS_API_KEY', null),
];