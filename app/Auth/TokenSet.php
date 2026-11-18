<?php

namespace App\Auth;

use Carbon\CarbonImmutable;

/**
 * An access token and the refresh token that renews it, as a Sign-in Provider issued them. The
 * refresh token is null only if the provider never sent one.
 */
final readonly class TokenSet
{
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public CarbonImmutable $expiresAt,
    ) {}
}
