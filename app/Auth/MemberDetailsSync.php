<?php

namespace App\Auth;

use App\Enums\MemberSyncOutcome;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Refreshes one Account's Member Details for the daily sync, so the Controller Rule, worked out
 * live from rating and subdivision, never goes by stale ones.
 *
 * With tokens that still work it reads the Sign-in Provider's /api/user and applies the full
 * details, as sign-in does. Otherwise it falls back to VATSIM's public member record, which only
 * knows rating and subdivision. A record that can't be read leaves the Account as it is; a 429 or
 * an outage stops the whole run instead (MemberSyncStopped), to resume the next day. An Account
 * found suspended or inactive is signed out everywhere; its roles and grants are left for an
 * admin, since a suspension can be temporary.
 */
final class MemberDetailsSync
{
    private const string PUBLIC_RECORD_URL = 'https://api.vatsim.net/v2/members/%d';

    public function __construct(
        private SignInTokens $tokens,
        private OAuthClient $client,
        private Accounts $accounts,
    ) {}

    /**
     * @throws MemberSyncStopped
     */
    public function sync(User $user): MemberSyncOutcome
    {
        $outcome = $this->viaToken($user) ?? $this->viaPublicRecord($user);

        $user->refresh()->forceFill(['details_synced_at' => now()])->save();

        if ($outcome !== MemberSyncOutcome::Skipped && $user->rating !== null && $user->rating->value < 1) {
            $this->endSessions($user);

            return MemberSyncOutcome::Suspended;
        }

        return $outcome;
    }

    /**
     * Null when the Account has no tokens that work, so the public record is next.
     *
     * @throws MemberSyncStopped
     */
    private function viaToken(User $user): ?MemberSyncOutcome
    {
        try {
            $data = $this->memberWithToken($user);
        } catch (OAuthRequestFailed $failure) {
            throw new MemberSyncStopped("The sign-in provider couldn’t be reached ({$failure->getMessage()})");
        }

        $details = $data !== null ? MemberDetails::fromSignIn($data) : null;

        if ($details === null || ! $details->sharedVatsimDetails || $details->cid !== $user->vatsim_cid) {
            return null;
        }

        $this->accounts->apply($details);

        return MemberSyncOutcome::ViaToken;
    }

    /**
     * The member's /api/user, or null without a token that works. A token turned down (401) is
     * refreshed once and tried again before it's given up on.
     *
     * @return array<string, mixed>|null
     *
     * @throws OAuthRequestFailed on an outage
     */
    private function memberWithToken(User $user): ?array
    {
        foreach ([false, true] as $refresh) {
            $token = $this->tokens->accessToken($user, $refresh);

            if ($token === null) {
                return null;
            }

            try {
                return $this->client->member($token);
            } catch (OAuthRequestFailed $failure) {
                if (! $failure->isPermanent()) {
                    throw $failure;
                }

                if ($failure->status !== 401) {
                    return null;
                }
            }
        }

        // Turned down even freshly refreshed: it'll never work.
        $this->tokens->forget($user);

        return null;
    }

    /**
     * @throws MemberSyncStopped
     */
    private function viaPublicRecord(User $user): MemberSyncOutcome
    {
        try {
            $response = Http::acceptJson()->connectTimeout(5)->timeout(10)->get(sprintf(self::PUBLIC_RECORD_URL, $user->vatsim_cid));
        } catch (ConnectionException) {
            throw new MemberSyncStopped('The VATSIM API didn’t answer');
        }

        if ($response->status() === 429 || $response->serverError()) {
            throw new MemberSyncStopped("The VATSIM API answered HTTP {$response->status()}");
        }

        // Sandbox CIDs, say, which the live API has never heard of.
        $details = $response->successful() && is_array($response->json())
            ? MemberDetails::fromPublicRecord($user->vatsim_cid, $response->json())
            : null;

        if ($details === null) {
            return MemberSyncOutcome::Skipped;
        }

        $this->accounts->apply($details);

        return MemberSyncOutcome::ViaPublicRecord;
    }

    /**
     * Sessions are kept in the database (SESSION_DRIVER=database, the default); with another
     * driver they simply run out on their own.
     */
    private function endSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
