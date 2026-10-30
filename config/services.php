<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | Credentials for VATSIM Connect, Handover and the VATSIM API.
    |
    */

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

    // VATSIM Scandinavia's Handover, used instead of VATSIM Connect when AUTH_PROVIDER=handover.
    'handover' => [
        'client_id' => env('HANDOVER_CLIENT_ID'),
        'client_secret' => env('HANDOVER_SECRET'),
        'redirect' => env('HANDOVER_REDIRECT') ?: rtrim((string) env('APP_URL', 'http://localhost'), '/').'/auth/handover/callback',
        'base_url' => env('HANDOVER_BASE_URL') ?: 'https://handover.vatsim-scandinavia.org',
    ],

];
