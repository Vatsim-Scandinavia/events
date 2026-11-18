<?php

namespace App\Enums;

/**
 * Where people sign in: VATSIM Connect, or VATSIM Scandinavia's Handover, which stands in for it.
 *
 * Each install uses one, chosen with AUTH_PROVIDER. Both vouch for a VATSIM CID, so switching
 * keeps everyone's account. The value is also its config key in services and the /auth/{provider} path.
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

    /**
     * Where its OAuth endpoints live, without a trailing slash: /oauth/authorize, /oauth/token
     * and /api/user, the same on both.
     */
    public function baseUrl(): string
    {
        return rtrim((string) $this->setting('base_url'), '/');
    }

    public function clientId(): string
    {
        return (string) $this->setting('client_id');
    }

    public function clientSecret(): string
    {
        return (string) $this->setting('client_secret');
    }

    /**
     * The callback registered for the client, built from config rather than the request, so a
     * forwarded host header can never change it.
     */
    public function redirectUri(): string
    {
        return (string) $this->setting('redirect');
    }

    public function label(): string
    {
        return match ($this) {
            self::Vatsim => 'VATSIM',
            self::Handover => 'VATSIM Scandinavia',
        };
    }

    private function setting(string $key): mixed
    {
        return config("services.{$this->value}.{$key}");
    }
}
