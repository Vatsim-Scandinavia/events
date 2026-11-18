<?php

namespace App\Enums;

/**
 * Why a sign-in didn't go through, each with what the Member is told. Under Handover, Cancelled,
 * DetailsNotShared and Suspended never reach the Hub (Handover handles them on its own pages),
 * but they're handled whichever provider is in use.
 */
enum SignInFailure: string
{
    /** They declined at the provider, or it sent back any other error. */
    case Cancelled = 'cancelled';

    /** The state didn't match: the sign-in took too long or began in another tab. */
    case Expired = 'expired';

    /** The provider timed out, failed, or wouldn't swap the code for tokens. */
    case ProviderUnavailable = 'provider_unavailable';

    /** Something the Hub needs didn't come back: CID, name, email, VATSIM details or continuous access. */
    case DetailsNotShared = 'details_not_shared';

    /** Rated suspended or inactive on the network. */
    case Suspended = 'suspended';

    public function message(SignInProvider $provider): string
    {
        return match ($this) {
            self::Cancelled => "Sign-in was cancelled on {$provider->label()}.",
            self::Expired => 'Your sign-in took too long or was started in another tab. Please try again.',
            self::ProviderUnavailable => "{$provider->label()} isn’t responding right now. Please try again in a few minutes.",
            self::DetailsNotShared => "{$provider->label()} didn’t share everything the Hub needs. Please allow access to your name, email, VATSIM details and continued access when you sign in.",
            self::Suspended => 'Your VATSIM account is suspended or inactive, so you can’t sign in.',
        };
    }

    /**
     * Whether trying again could help, so the message offers a "Try again" link.
     */
    public function offersRetry(): bool
    {
        return $this !== self::Suspended;
    }
}
