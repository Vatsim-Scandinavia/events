<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | VATSIM Scandinavia Handover OAuth Configuration
    |--------------------------------------------------------------------------
    */

    'vatsim' => [
        'client_id' => env('OAUTH_CLIENT_ID'),
        'client_secret' => env('OAUTH_CLIENT_SECRET'),
        'base_url' => env('OAUTH_BASE_URL'),
        'redirect' => env('OAUTH_REDIRECT_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Control Center API Configuration
    |--------------------------------------------------------------------------
    */

    'control_center' => [
        'api_url'     => env('CONTROL_CENTER_API_URL'),
        'api_token'   => env('CONTROL_CENTER_API_TOKEN'),
        'booking_tag' => env('CONTROL_CENTER_BOOKING_TAG', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | VATSIM Booking API Configuration
    |--------------------------------------------------------------------------
    */

    'vatsim_booking' => [
        'api_url' => env('VATSIM_BOOKING_API_URL'),
        'api_key' => env('VATSIM_BOOKING_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Discord Configuration
    |--------------------------------------------------------------------------
    */

    'discord' => [
        'webhook_url' => env('DISCORD_WEBHOOK_URL'),
        'bot_token' => env('DISCORD_BOT_TOKEN'),
        'bot_api_url' => env('DISCORD_API_URL'),
        'bot_api_token' => env('DISCORD_API_TOKEN'),
        'mention_role_id' => env('DISCORD_MENTION_ROLE_ID'), // Role ID to mention for new events
        'guild_id' => env('DISCORD_GUILD_ID'), // Restrict to specific guild/server
    ],

];
