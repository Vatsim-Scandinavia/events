<?php

namespace App\Auth;

/**
 * The stand-in sign-in that lets anyone become any user, until VATSIM Connect is wired up.
 *
 * It is off unless AUTH_DEV_LOGIN is set, and never available in production, whatever
 * the configuration says.
 */
final class DevLogin
{
    public static function enabled(): bool
    {
        return (bool) config('auth.dev_login') && ! app()->isProduction();
    }
}
