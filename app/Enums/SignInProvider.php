<?php

namespace App\Enums;

/**
 * Where people sign in: VATSIM Connect, or VATSIM Scandinavia's Handover, which stands in for it.
 *
 * Each install uses one, chosen with AUTH_PROVIDER. Both vouch for a VATSIM CID, so switching
 * keeps everyone's account. The value is also the Socialite driver and the /auth/{provider} path.
 */
enum SignInProvider: string
{
    case Vatsim = 'vatsim';
    case Handover = 'handover';

    /**
     * An unknown AUTH_PROVIDER falls back to VATSIM Connect rather than breaking every page.
     */
    public static function configured(): self
    {
        return self::tryFrom((string) config('auth.sign_in_provider')) ?? self::Vatsim;
    }

    public function label(): string
    {
        return match ($this) {
            self::Vatsim => 'VATSIM',
            self::Handover => 'VATSIM Scandinavia',
        };
    }
}
