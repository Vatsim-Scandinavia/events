<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Discord Webhook
    |--------------------------------------------------------------------------
    |
    | Set the webhook used to post event notifications
    |
    */

    'webhook' => env('DISCORD_WEBHOOK', null),

    /*
    |--------------------------------------------------------------------------
    | Discord Mention Role
    |--------------------------------------------------------------------------
    |
    | Set the role to mention in the Discord post
    |
    */

    'mention_role' => env('DISCORD_MENTION_ROLE', null),

];
