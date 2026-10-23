<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | The addresses of the reverse proxy or load balancer in front of the hub,
    | comma separated, or "*" to trust whichever address the request came from.
    | Their X-Forwarded-* headers then tell the app the visitor used HTTPS, so
    | links and the VATSIM sign-in redirect use https and HSTS is sent. Leave
    | empty when nothing sits in front of the app.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
