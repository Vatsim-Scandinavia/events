<?php

return [

    'vatsim' => [
        'enabled' => env('VATSIM_ENABLED', true),
        'base_url' => env('VATSIM_BASE_URL', 'https://auth.vatsim.net'),
        'client_id' => env('VATSIM_CLIENT_ID'),
        'client_secret' => env('VATSIM_CLIENT_SECRET'),
        'redirect' => env('VATSIM_REDIRECT_URI', '/auth/vatsim/callback'),
    ],

    'handover' => [
        'enabled' => env('HANDOVER_ENABLED', false),
        'base_url' => env('HANDOVER_BASE_URL', 'https://handover.vatsim-scandinavia.org'),
        'client_id' => env('HANDOVER_CLIENT_ID'),
        'client_secret' => env('HANDOVER_CLIENT_SECRET'),
        'redirect' => env('HANDOVER_REDIRECT_URI', '/auth/handover/callback'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

];
