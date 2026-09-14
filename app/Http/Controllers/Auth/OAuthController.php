<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use JsonException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use UnexpectedValueException;

class OAuthController extends Controller
{
    public function login(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'providerName' => config('oauth.provider') === 'handover' ? 'Handover' : 'VATSIM Connect',
            'configured' => $this->configured(),
            'error' => $request->session()->get('oauth_error'),
        ]);
    }

    public function redirect(Request $request): RedirectResponse
    {
        abort_unless($this->configured(), 503, 'OAuth authentication has not been configured.');
        $request->session()->put('oauth_provider', config('oauth.provider'));

        return Socialite::driver(config('oauth.provider'))->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->configured() || $request->session()->pull('oauth_provider') !== config('oauth.provider')) {
            return $this->failed($request);
        }

        if ($request->has('error')) {
            return $this->failed($request);
        }

        try {
            $identity = Socialite::driver(config('oauth.provider'))->user();
        } catch (InvalidStateException|GuzzleException|JsonException|UnexpectedValueException $exception) {
            return $this->failed($request);
        }

        $profile = [
            'vatsim_cid' => (string) $identity->getId(),
            'name' => $identity->getName(),
            'email' => $identity->getEmail(),
        ];

        if (Validator::make($profile, [
            'vatsim_cid' => ['required', 'regex:/^[1-9][0-9]{0,19}$/'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ])->fails()) {
            return $this->failed($request);
        }

        try {
            $user = User::firstOrNew(['vatsim_cid' => $profile['vatsim_cid']]);
            $user->fill($profile);
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }
            $user->save();
        } catch (UniqueConstraintViolationException $exception) {
            return $this->failed($request, 'This identity conflicts with an existing account. Please contact support.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('home');
    }

    private function configured(): bool
    {
        $provider = config('oauth.provider');

        return in_array($provider, ['vatsim', 'handover'], true)
            && filled(config('services.'.$provider.'.client_id'))
            && filled(config('services.'.$provider.'.client_secret'))
            && filled(config('services.'.$provider.'.base_url'));
    }

    private function failed(Request $request, string $message = 'Sign-in could not be completed. Please try again.'): RedirectResponse
    {
        $request->session()->forget(['state', 'oauth_provider']);

        return to_route('login')->with('oauth_error', $message);
    }
}
