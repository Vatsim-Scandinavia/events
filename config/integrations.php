<?php

use App\Integrations\ControlCenter\ControlCenter;

return [

    /*
    |--------------------------------------------------------------------------
    | Integrations
    |--------------------------------------------------------------------------
    |
    | The Integrations the Hub offers (classes extending App\Integrations\Integration).
    | Admins switch them on and set them up on the Integrations admin page.
    |
    */

    'registered' => [
        ControlCenter::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings Set On The Server
    |--------------------------------------------------------------------------
    |
    | Setting an Integration's setting here locks it: the app shows it as set by
    | the server and admins can't change it. Keyed by the Integration's key, then
    | the setting's key, each read from the Setting's environment variable.
    |
    */

    'settings' => [
        'control-center' => [
            'url' => env('CONTROL_CENTER_URL'),
            'token' => env('CONTROL_CENTER_TOKEN'),
            'areas' => env('CONTROL_CENTER_AREAS'),
        ],
    ],

];
