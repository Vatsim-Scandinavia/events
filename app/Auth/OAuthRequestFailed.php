<?php

namespace App\Auth;

use RuntimeException;

/**
 * The Sign-in Provider turned a request down or couldn't be reached. Never carries a token or a
 * code, so it is safe to log.
 */
final class OAuthRequestFailed extends RuntimeException
{
    /**
     * @param  int|null  $status  The HTTP status, or null when there was no answer (a timeout).
     * @param  string|null  $error  The OAuth error code it sent, such as "invalid_grant".
     */
    public function __construct(public readonly ?int $status, public readonly ?string $error = null)
    {
        parent::__construct($status === null
            ? 'The sign-in provider didn’t answer.'
            : "The sign-in provider answered HTTP {$status}".($error !== null ? " ({$error})" : '').'.');
    }

    /**
     * The provider refused it (any 4xx), so trying again won't help: an expired or revoked
     * refresh token, say. Connect answers a revoked one with 401 invalid_request, not invalid_grant.
     */
    public function isPermanent(): bool
    {
        return $this->status !== null && $this->status >= 400 && $this->status < 500;
    }
}
