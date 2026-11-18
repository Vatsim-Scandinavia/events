<?php

namespace App\Auth;

use App\Enums\SignInProvider;
use App\Models\SignInToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * The tokens the Sign-in Provider issued each Account, kept so the daily sync can read fresh
 * Member Details with them. One pair per Account, encrypted, never in the session.
 *
 * Tokens are refreshed lazily, when one is about to be used and has (nearly) expired, under a lock
 * per Account: providers rotate refresh tokens, so two refreshes at once would look like a replay.
 * A token the provider refuses (any 4xx) is deleted and nothing else changes; the Account stays
 * signed in, keeps its roles, and gets a new pair next time it signs in. An outage keeps it.
 * Signing out leaves them alone. Neither provider can revoke tokens, so deleting the Account is
 * the only thing that removes them.
 */
final class SignInTokens
{
    /** How close to expiry a token is refreshed rather than used. */
    private const int REFRESH_MARGIN_SECONDS = 300;

    public function __construct(private OAuthClient $client) {}

    /**
     * Keep the tokens the member just signed in with, replacing any earlier ones.
     */
    public function keep(User $user, TokenSet $tokens): void
    {
        SignInToken::query()->updateOrCreate(['user_id' => $user->id], [
            'provider' => SignInProvider::configured(),
            'access_token' => $tokens->accessToken,
            'refresh_token' => $tokens->refreshToken,
            'expires_at' => $tokens->expiresAt,
        ]);
    }

    /**
     * An access token for the member that works now, refreshed first if it had (nearly) expired,
     * or whatever its expiry says when $refresh (the provider just turned it down). Null when they
     * have none that works any more.
     *
     * @throws OAuthRequestFailed when the provider couldn't be reached; the tokens are kept
     */
    public function accessToken(User $user, bool $refresh = false): ?string
    {
        $token = $this->stored($user);

        if ($token === null || (! $refresh && $this->isFresh($token))) {
            return $token?->access_token;
        }

        $refused = $token->access_token;

        return Cache::lock("sign-in-tokens:{$user->id}", 30)->block(10, function () use ($user, $refresh, $refused) {
            // Someone else may have refreshed it while this waited for the lock.
            $token = $this->stored($user);

            if ($token === null || ($this->isFresh($token) && (! $refresh || $token->access_token !== $refused))) {
                return $token?->access_token;
            }

            if ($token->refresh_token === null) {
                $token->delete();

                return null;
            }

            try {
                $tokens = $this->client->refresh($token->refresh_token);
            } catch (OAuthRequestFailed $failure) {
                if ($failure->isPermanent()) {
                    $token->delete();

                    return null;
                }

                throw $failure;
            }

            $token->update(['access_token' => $tokens->accessToken, 'refresh_token' => $tokens->refreshToken, 'expires_at' => $tokens->expiresAt]);

            return $tokens->accessToken;
        });
    }

    /**
     * Drop the member's tokens: the provider turned even a freshly refreshed one down.
     */
    public function forget(User $user): void
    {
        SignInToken::query()->where('user_id', $user->id)->delete();
    }

    /**
     * The member's tokens from the configured provider. Ones from another provider (the Hub
     * switched between Connect and Handover) can never work, so they're dropped.
     */
    private function stored(User $user): ?SignInToken
    {
        $token = SignInToken::query()->where('user_id', $user->id)->first();

        if ($token !== null && $token->provider !== SignInProvider::configured()) {
            $token->delete();

            return null;
        }

        return $token;
    }

    private function isFresh(SignInToken $token): bool
    {
        return $token->expires_at->isAfter(now()->addSeconds(self::REFRESH_MARGIN_SECONDS));
    }
}
