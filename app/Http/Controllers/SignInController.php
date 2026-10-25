<?php

namespace App\Http\Controllers;

use App\Auth\ControllerRole;
use App\Enums\ControllerRating;
use App\Enums\SignInProvider;
use App\Models\User;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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

    public function callback(Request $request, SignInProvider $provider, ControllerRole $controllerRole): RedirectResponse
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

        if (blank($vatsimUser->getId())) {
            return $this->failed("{$provider->label()} did not share your CID. Please allow access to your VATSIM details.");
        }

        $user = User::query()->firstOrNew(['vatsim_cid' => (int) $vatsimUser->getId()]);
        $user->fill([
            'name' => $vatsimUser->getName() ?: "VATSIM {$vatsimUser->getId()}",
            'email' => $vatsimUser->getEmail(),
        ]);

        $ratingId = Arr::get($vatsimUser->getRaw(), 'vatsim.rating.id');
        if ($ratingId !== null && ($rating = ControllerRating::tryFrom((int) $ratingId))) {
            $user->rating = $rating;
        }

        // Null when they aren't in a subdivision; it decides the automatic Controller role.
        $user->subdivision = Arr::get($vatsimUser->getRaw(), 'vatsim.subdivision.id') ?: null;

        $user->save();
        $controllerRole->sync($user);

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
