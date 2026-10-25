<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | The addresses of the reverse proxy or load balancer in front of the hub,
    | comma separated. Their X-Forwarded-Proto, -Port and -For headers are then
    | believed, so the app knows the visitor used HTTPS (links use https and
    | HSTS is sent) and who they are (for rate limits). X-Forwarded-Host never
    | is (see bootstrap/app.php). Leave empty when nothing sits in front.
    |
    | "*" trusts whichever address a request came from. Only use it when the
    | app can't be reached except through the proxy, or anyone could claim to
    | be a proxy and dodge the sign-in rate limit.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
