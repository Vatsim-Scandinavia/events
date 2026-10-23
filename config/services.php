<?php

return [

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

    'vatsim' => [
        'client_id' => env('VATSIM_CONNECT_CLIENT_ID'),
        'client_secret' => env('VATSIM_CONNECT_SECRET'),
        // Built from APP_URL rather than the address in the browser, since VATSIM only accepts
        // the exact callback registered for the client (locally 127.0.0.1, never localhost).
        'redirect' => env('VATSIM_CONNECT_REDIRECT') ?: rtrim((string) env('APP_URL', 'http://localhost'), '/').'/auth/vatsim/callback',
        // https://auth-dev.vatsim.net is VATSIM's sandbox for local development.
        'base_url' => env('VATSIM_CONNECT_BASE_URL') ?: 'https://auth.vatsim.net',
        // The daily refresh of ratings and subdivisions from the live VATSIM API. Switch it off
        // where people sign in with sandbox accounts, which the live API doesn't know.
        'sync' => (bool) env('VATSIM_SYNC', true),
    ],

    'discord' => [
        // A channel webhook (Server Settings → Integrations → Webhooks). Published events are
        // announced there; leave empty to announce nothing.
        'events_webhook' => env('DISCORD_EVENTS_WEBHOOK'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
