<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SyncOAuthUser;
use App\Http\Controllers\Controller;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use JsonException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use UnexpectedValueException;

class OAuthController extends Controller
{
    public function index(): Response
    {
        $providers = [];

        foreach (config('auth.oauth_providers') as $name => $provider) {
            if ($this->isConfigured($name)) {
                $providers[] = ['id' => $name, 'name' => $provider['name'], 'primary' => $name === 'vatsim'];
            }
        }

        return Inertia::render('auth/login', ['providers' => $providers]);
    }

    public function redirect(Request $request, string $provider): SymfonyRedirectResponse
    {
        abort_unless($this->isConfigured($provider), 404);

        $request->session()->put('oauth.provider', $provider);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider, SyncOAuthUser $syncUser): RedirectResponse
    {
        abort_unless($this->isConfigured($provider), 404);

        if ($request->session()->pull('oauth.provider') !== $provider
            || $request->has('error')
            || ! is_string($request->input('code'))
            || $request->input('code') === ''
            || ! is_string($request->input('state'))) {
            $request->session()->forget('state');

            return to_route('login')->withErrors(['oauth' => 'Sign-in was cancelled or expired. Please try again.']);
        }

        try {
            $identity = Socialite::driver($provider)->user();

            if (! $identity instanceof SocialiteUser) {
                throw new UnexpectedValueException('An OAuth 2 user is required.');
            }

            $user = $syncUser->handle($provider, $identity);
        } catch (InvalidStateException) {
            return to_route('login')->withErrors(['oauth' => 'Your sign-in session expired. Please try again.']);
        } catch (GuzzleException|JsonException|UnexpectedValueException $exception) {
            Log::warning('OAuth sign-in failed.', ['provider' => $provider, 'exception' => $exception::class]);

            return to_route('login')->withErrors(['oauth' => 'Unable to sign in with this provider. Please try again.']);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Inertia::clearHistory();

        return to_route('home');
    }

    private function isConfigured(string $provider): bool
    {
        if (! array_key_exists($provider, config('auth.oauth_providers'))) {
            return false;
        }

        $service = config('services.'.$provider, []);

        return ($service['enabled'] ?? false)
            && filled($service['client_id'] ?? null)
            && filled($service['client_secret'] ?? null)
            && filled($service['redirect'] ?? null)
            && filled($service['base_url'] ?? null);
    }
}
