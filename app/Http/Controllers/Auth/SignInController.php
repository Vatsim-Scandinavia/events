<?php

namespace App\Http\Controllers\Auth;

use App\Auth\Controllers;
use App\Enums\SignInFailure;
use App\Enums\SignInProvider;
use App\Exceptions\SignInFailed;
use App\Http\Controllers\Controller;
use App\Models\SignInToken;
use App\Models\User;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Signing in and out. Members sign in through the configured Sign-in Provider (Socialite does the
 * OAuth round trip, with state and PKCE) as the CID it vouches for. Its Member Details are written
 * onto their Account and its tokens kept for the daily sync.
 *
 * With APP_ENV=local the sign-in page also offers a one-click sign-in as each of the VATSIM Connect
 * sandbox's test members, the CIDs the seeder uses. APP_ENV=local is the only guard, as in Control
 * Center: outside it the CIDs aren't sent and the dev route is a 404, whatever the request says. A
 * server must never run with APP_ENV=local.
 */
class SignInController extends Controller
{
    /** The sandbox's test members, CIDs 10000001 to 10000010. */
    private const array DEV_CIDS = [10000001, 10000002, 10000003, 10000004, 10000005, 10000006, 10000007, 10000008, 10000009, 10000010];

    /**
     * The sign-in page. A sign-in link passes the page it was on as `return`, which the buttons
     * carry on, so people land back there afterwards.
     */
    public function show(Request $request): Response
    {
        $returnTo = $request->query('return');
        $provider = SignInProvider::configured();

        return Inertia::render('Auth/SignIn', [
            'provider' => [
                'label' => "Sign in with {$provider->label()}",
                'url' => route('auth.redirect', $provider, absolute: false),
            ],
            'returnTo' => is_string($returnTo) && self::isLocalPath($returnTo) ? $returnTo : null,
            'devCids' => app()->environment('local') ? self::DEV_CIDS : null,
        ]);
    }

    /**
     * Send them to the provider. Inertia visits bounced here by the auth middleware get a full page
     * visit, since the provider is another origin.
     */
    public function redirect(Request $request, SignInProvider $provider): SymfonyResponse
    {
        $this->ensureConfigured($provider);
        $this->rememberReturnPath($request->query('return'));

        return Inertia::location(Socialite::driver($provider->value)->redirect()->getTargetUrl());
    }

    /**
     * Take them back from the provider and sign them in.
     *
     * @throws SignInFailed which lands them back where they started, with the reason
     */
    public function callback(Request $request, SignInProvider $provider, Controllers $controllers): RedirectResponse
    {
        $this->ensureConfigured($provider);
        $member = $this->member($request, $provider);

        if ($member->getId() === null || ! $member->isComplete) {
            throw new SignInFailed(SignInFailure::DetailsNotShared);
        }

        if ($member->rating?->isSuspended()) {
            throw new SignInFailed(SignInFailure::Suspended);
        }

        $user = User::query()->firstOrNew(['vatsim_cid' => $member->getId()])->applySignInDetails($member);
        SignInToken::keep($user, $provider, $member);
        $controllers->signedIn($user);

        return $this->signInAs($request, $user);
    }

    /**
     * Sign in as a sandbox test member, creating their Account the first time.
     */
    public function dev(Request $request, int $cid): RedirectResponse
    {
        abort_unless(app()->environment('local') && in_array($cid, self::DEV_CIDS, true), 404);

        $this->rememberReturnPath($request->input('return'));

        return $this->signInAs($request, User::query()->firstOrCreate(['vatsim_cid' => $cid], ['name' => "VATSIM {$cid}"]));
    }

    /**
     * Sign out, however they signed in. Their tokens stay, for the daily sync.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * The member the provider vouches for. The state is used once, whatever happens, so a callback
     * can never be replayed.
     *
     * @throws SignInFailed
     */
    private function member(Request $request, SignInProvider $provider): SocialiteUser
    {
        if ($request->has('error')) {
            $request->session()->forget(['state', 'code_verifier']);

            throw new SignInFailed(SignInFailure::Cancelled);
        }

        try {
            return Socialite::driver($provider->value)->user();
        } catch (InvalidStateException) {
            $request->session()->forget('code_verifier');

            throw new SignInFailed(SignInFailure::Expired);
        } catch (TransferException $exception) {
            throw SignInFailed::providerUnavailable($exception);
        }
    }

    /**
     * Sign the user in on a fresh session and CSRF token, and send them where they were going.
     */
    private function signInAs(Request $request, User $user): RedirectResponse
    {
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->intended('/');
    }

    /**
     * Remember where to land after signing in, but only a path on this site, so the link can't send
     * people elsewhere.
     */
    private function rememberReturnPath(mixed $returnTo): void
    {
        if (is_string($returnTo) && self::isLocalPath($returnTo)) {
            redirect()->setIntendedUrl(url($returnTo));
        }
    }

    /**
     * Control characters are refused outright, as browsers strip some of them from URLs.
     */
    private static function isLocalPath(string $path): bool
    {
        return str_starts_with($path, '/') && ! str_starts_with($path, '//') && ! str_contains($path, '\\')
            && ! preg_match('/[\x00-\x1F\x7F]/', $path);
    }

    private function ensureConfigured(SignInProvider $provider): void
    {
        abort_unless($provider === SignInProvider::configured(), 404);
    }
}
