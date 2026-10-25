<?php

namespace App\Auth;

/**
 * The stand-in sign-in that lets anyone become any user, for local development.
 *
 * It is off unless AUTH_DEV_LOGIN is set, and only ever available on a developer's own machine
 * (APP_ENV=local, or testing), so a staging or production server can't switch it on.
 */
final class DevLogin
{
    public static function enabled(): bool
    {
        return (bool) config('auth.dev_login') && app()->environment('local', 'testing');
    }
}
