<?php

namespace App\Providers\Socialite;

class HandoverProvider extends VatsimProvider
{
    protected function baseUrl(): string
    {
        return rtrim(config('services.handover.base_url'), '/');
    }
}
