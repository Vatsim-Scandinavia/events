<?php

namespace App\Http\Controllers;

use App\Auth\Accounts;
use App\Auth\Controllers;
use App\Auth\MemberDetails;
use App\Enums\SignInProvider;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Sign-in through the configured sign-in provider: send people there, then sign them in as the CID it vouches for.
 */
class SignInController extends Controller
{
    /**
     * Inertia visits bounced here by the auth middleware get a full page visit, since the provider is another origin.
     *
     * A sign-in link can pass the page it was on as `return`, so people land back there afterwards.
     */
    public function redirect(Request $request, SignInProvider $provider): SymfonyResponse
    {
        $this->ensureConfigured($provider);

        $returnTo = $request->query('return');

        if (is_string($returnTo) && $this->isLocalPath($returnTo)) {
            redirect()->setIntendedUrl(url($returnTo));
        }

        return Inertia::location(Socialite::driver($provider->value)->redirect()->getTargetUrl());
    }

    public function callback(Request $request, SignInProvider $provider, Accounts $accounts, Controllers $controllers): RedirectResponse
    {
        $this->ensureConfigured($provider);

        // The provider sends people back with an error when they decline to share their details.
        if ($request->has('error')) {
            return $this->failed("Sign-in was cancelled on {$provider->label()}.");
        }

        try {
            $vatsimUser = Socialite::driver($provider->value)->user();
        } catch (InvalidStateException|GuzzleException) {
            return $this->failed("Sign-in with {$provider->label()} failed. Please try again.");
        }

        $details = MemberDetails::fromSignIn($vatsimUser->getRaw());

        if ($details === null) {
            return $this->failed("{$provider->label()} did not share your CID. Please allow access to your VATSIM details.");
        }

        $user = $accounts->apply($details);
        $controllers->signedIn($user);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    private function ensureConfigured(SignInProvider $provider): void
    {
        abort_unless($provider === SignInProvider::configured(), 404);
    }

    /**
     * Only paths on this site, so the link can't be used to send people elsewhere after signing in.
     * Control characters are refused outright, as browsers strip some of them from URLs.
     */
    private function isLocalPath(string $path): bool
    {
        return str_starts_with($path, '/') && ! str_starts_with($path, '//') && ! str_contains($path, '\\')
            && ! preg_match('/[\x00-\x1F\x7F]/', $path);
    }

    private function failed(string $message): RedirectResponse
    {
        return redirect('/')->withErrors(['signIn' => $message]);
    }
}
