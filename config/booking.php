<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Control Center API URL
    |--------------------------------------------------------------------------
    |
    | Set the URL for the Control Center API
    |
    */

    'cc_api_url' => env('CC_API_URL', null),

    /*
    |--------------------------------------------------------------------------
    | Control Center API Token
    |--------------------------------------------------------------------------
    |
    | Set the Control Center API Token
    |
    */

    'cc_api_token' => env('CC_API_TOKEN', null),

    /*
    |--------------------------------------------------------------------------
    | Discord API URL Endpoint
    |--------------------------------------------------------------------------
    |
    | Set the URL for the Discord API
    |
    */
    'discord_api_url' => env('DISCORD_API_URL', null),

    /*
    |--------------------------------------------------------------------------
    | Discord API Token
    |--------------------------------------------------------------------------
    |
    | Set the Discord API Token
    |
    */
    'discord_api_token' => env('DISCORD_API_TOKEN', null),

    /*
    |--------------------------------------------------------------------------
    | Discord API Retry Delay Times
    |--------------------------------------------------------------------------
    |
    | Set the Discord API Retry Delay Times
    |
    */
    'booking.api_retry_times' => env('API_RETRY_TIMES', 3),

    /*
    |--------------------------------------------------------------------------
    | Discord API Retry Delay in ms
    |--------------------------------------------------------------------------
    |
    | Set the Discord API Retry Delay in ms
    |
    */
    'booking.api_retry_delay' => env('API_RETRY_DELAY', 1000),

];