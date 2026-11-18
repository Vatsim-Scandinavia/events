<?php

namespace App\Jobs;

use App\Enums\SignInProvider;
use App\Models\SignInToken;
use App\Models\User;
use DateTimeInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Throwable;

/**
 * Brings one Account's Member Details up to date from the Sign-in Provider with the tokens it
 * signed in with, so email and name stay fresh and the Controller Rule, worked out live from
 * rating and subdivision, never goes by stale ones.
 *
 * A token the provider refuses is deleted, and the Account then waits for its next sign-in. An
 * outage or a rate limit is retried later instead: once a few jobs in a row hit one, every sync
 * job holds off for a while. An Account found suspended or inactive is signed out everywhere; its
 * roles and grants are left for an admin, since a suspension can be temporary.
 */
class SyncMemberDetails implements ShouldQueue
{
    use Queueable;

    /** Exceptions other than an outage (say a refused client secret) fail the job after a few tries. */
    public int $maxExceptions = 10;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 1800];

    public function __construct(public int $userId) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            // Providers rotate refresh tokens, so two refreshes at once would look like a replay.
            (new WithoutOverlapping((string) $this->userId))->releaseAfter(30)->expireAfter(120),
            (new ThrottlesExceptions(maxAttempts: 5, decaySeconds: 10 * 60))
                ->by('sign-in-provider')
                ->backoff(5)
                ->when(fn (Throwable $exception) => self::isTransient($exception)),
        ];
    }

    /**
     * Retries are bounded by time rather than attempts, as the throttle releases jobs too; other
     * exceptions are bounded by $maxExceptions.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(12);
    }

    public function handle(): void
    {
        $user = User::query()->find($this->userId);
        $token = $user?->signInToken;

        if ($user === null || $token === null) {
            return;
        }

        // Tokens from the other provider (the Hub switched between Connect and Handover) can never work.
        if ($token->provider !== SignInProvider::configured()) {
            $token->deleteUnlessReplaced();

            return;
        }

        $member = $this->member($token);

        if ($member === null || ! $member->isComplete || $member->getId() !== $user->vatsim_cid) {
            return;
        }

        // Signed out first, so a save that fails can never leave a suspended member signed in.
        if ($member->rating?->isSuspended()) {
            $this->endSessions($user);
        }

        $user->applySignInDetails($member)->forceFill(['details_synced_at' => now()])->save();
    }

    /**
     * The member as the provider describes them now, or null when their tokens no longer work. A
     * token turned down (401) is refreshed once and tried again before it's given up on.
     *
     * @throws TransferException on an outage or a rate limit, to be retried
     */
    private function member(SignInToken $token): ?SocialiteUser
    {
        foreach ([false, true] as $refresh) {
            $accessToken = $token->freshAccessToken($refresh);

            if ($accessToken === null) {
                return null;
            }

            try {
                return Socialite::driver($token->provider->value)->userFromToken($accessToken);
            } catch (ClientException $refused) {
                if (self::isTransient($refused)) {
                    throw $refused;
                }

                if ($refused->getResponse()->getStatusCode() !== 401) {
                    return null;
                }
            }
        }

        // Turned down even freshly refreshed: it'll never work.
        $token->deleteUnlessReplaced();

        return null;
    }

    /**
     * The provider is down, slow or rate limiting, rather than refusing the token.
     */
    private static function isTransient(Throwable $exception): bool
    {
        if ($exception instanceof ClientException) {
            return in_array($exception->getResponse()->getStatusCode(), [408, 429], true);
        }

        return $exception instanceof TransferException;
    }

    /**
     * Sessions are kept in the database (SESSION_DRIVER=database, the default); with another
     * driver they simply run out on their own.
     */
    private function endSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
