<?php

namespace App\Http\Controllers;

use App\Auth\SignIn;
use App\Auth\SignInFailed;
use App\Enums\SignInProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Sign-in through the configured Sign-in Provider: send people there, then sign them in as the CID
 * it vouches for. The rules live in App\Auth\SignIn.
 */
class SignInController extends Controller
{
    public function __construct(private SignIn $signIn) {}

    /**
     * Inertia visits bounced here by the auth middleware get a full page visit, since the provider
     * is another origin. A sign-in link can pass the page it was on as `return`, so people land
     * back there afterwards.
     */
    public function redirect(Request $request, SignInProvider $provider): SymfonyResponse
    {
        $this->ensureConfigured($provider);

        $returnTo = $request->query('return');

        return Inertia::location($this->signIn->start($request, is_string($returnTo) ? $returnTo : null));
    }

    /**
     * A sign-in that fails lands back on the page it started from, with the reason and, unless
     * trying again can't help, a "Try again" link.
     */
    public function callback(Request $request, SignInProvider $provider): RedirectResponse
    {
        $this->ensureConfigured($provider);

        try {
            return $this->signIn->complete($request);
        } catch (SignInFailed $failed) {
            if ($failed->reason->offersRetry()) {
                Inertia::flash('signInRetry', true);
            }

            return redirect()->intended('/')->withErrors(['signIn' => $failed->reason->message($provider)]);
        }
    }

    private function ensureConfigured(SignInProvider $provider): void
    {
        abort_unless($provider === SignInProvider::configured(), 404);
    }
}
