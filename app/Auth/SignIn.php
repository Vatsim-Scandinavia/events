<?php

namespace App\Auth;

use App\Enums\SignInFailure;
use App\Enums\SignInProvider;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Signing Members in through the Sign-in Provider. start() sends them there; complete() takes
 * them back, checks the round trip was theirs, writes their Member Details onto their Account,
 * keeps the tokens and signs them in; signInAs() is that last step on its own, shared with the
 * local dev links.
 *
 * The state and PKCE verifier live in the session for one attempt only. Codes and tokens are
 * never logged or put in the session.
 */
final class SignIn
{
    private const string STATE = 'sign_in.state';

    private const string VERIFIER = 'sign_in.verifier';

    public function __construct(
        private OAuthClient $client,
        private Accounts $accounts,
        private SignInTokens $tokens,
        private Controllers $controllers,
    ) {}

    /**
     * Where to send someone to sign in. A return path is remembered as where to land afterwards,
     * but only when it's a path on this site, so the link can't send people elsewhere.
     */
    public function start(Request $request, ?string $returnTo = null): string
    {
        if ($returnTo !== null && self::isLocalPath($returnTo)) {
            redirect()->setIntendedUrl(url($returnTo));
        }

        $state = Str::random(40);
        $verifier = OAuthClient::newCodeVerifier();
        $request->session()->put([self::STATE => $state, self::VERIFIER => $verifier]);

        return $this->client->authorizeUrl($state, $verifier);
    }

    /**
     * Finish signing in from the provider's callback, and return where to go next.
     *
     * @throws SignInFailed
     */
    public function complete(Request $request): RedirectResponse
    {
        // Used once, whatever happens, so a callback can never be replayed.
        $state = $request->session()->pull(self::STATE);
        $verifier = $request->session()->pull(self::VERIFIER);

        if ($request->has('error')) {
            throw new SignInFailed(SignInFailure::Cancelled);
        }

        if (! is_string($state) || ! is_string($verifier) || ! is_string($request->query('state')) || ! hash_equals($state, $request->query('state')) || ! is_string($request->query('code'))) {
            Log::info('Sign-in callback with a missing or mismatched state.');

            throw new SignInFailed(SignInFailure::Expired);
        }

        try {
            $tokens = $this->client->exchangeCode($request->query('code'), $verifier);
            $details = MemberDetails::fromSignIn($this->client->member($tokens->accessToken));
        } catch (OAuthRequestFailed $failure) {
            Log::warning('The sign-in provider could not complete a sign-in.', [
                'provider' => SignInProvider::configured()->value,
                'status' => $failure->status,
                'error' => $failure->error,
            ]);

            throw new SignInFailed(SignInFailure::ProviderUnavailable);
        }

        if ($details === null || ! $details->sharedVatsimDetails) {
            throw new SignInFailed(SignInFailure::DetailsNotShared);
        }

        if ($details->isSuspended()) {
            throw new SignInFailed(SignInFailure::Suspended);
        }

        $user = $this->accounts->apply($details);
        $this->tokens->keep($user, $tokens);
        $this->controllers->signedIn($user);

        return $this->signInAs($request, $user);
    }

    /**
     * Sign the user in on a fresh session and CSRF token, and send them where they were going.
     */
    public function signInAs(Request $request, User $user): RedirectResponse
    {
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->intended('/');
    }

    /**
     * Only paths on this site. Control characters are refused outright, as browsers strip some of
     * them from URLs.
     */
    public static function isLocalPath(string $path): bool
    {
        return str_starts_with($path, '/') && ! str_starts_with($path, '//') && ! str_contains($path, '\\')
            && ! preg_match('/[\x00-\x1F\x7F]/', $path);
    }
}
