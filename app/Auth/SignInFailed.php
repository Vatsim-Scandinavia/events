<?php

namespace App\Auth;

use App\Enums\SignInFailure;
use RuntimeException;

/**
 * A sign-in that didn't go through, and why.
 */
final class SignInFailed extends RuntimeException
{
    public function __construct(public readonly SignInFailure $reason)
    {
        parent::__construct("Sign-in failed: {$reason->value}.");
    }
}
