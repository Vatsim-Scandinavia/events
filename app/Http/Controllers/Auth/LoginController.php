<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LoginController extends Controller
{
    public function redirectToProvider(): RedirectResponse
    {
        return Socialite::driver('vatsim')->redirect();
    }

    public function handleProviderCallback(): RedirectResponse
    {
        $providerUser = Socialite::driver('vatsim')->user();

        $user = User::updateOrCreate(
            ['id' => $providerUser->getId()],
            [
                'email' => $providerUser->getEmail(),
                'first_name' => Arr::get($providerUser->user, 'data.personal.name_first'),
                'last_name' => Arr::get($providerUser->user, 'data.personal.name_last'),
                'last_login' => now(),
                'access_token' => $providerUser->token,
                'refresh_token' => $providerUser->refreshToken,
                'token_expires'  => $providerUser->expiresIn ? now()->addSeconds($providerUser->expiresIn)->timestamp : null,
            ]
        );

        Auth::login($user, true);

        return redirect()->intended('/');
    }

    public function logout(): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/');
    }
}
