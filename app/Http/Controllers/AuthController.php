<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Redirect to VATSIM OAuth provider
     */
    public function redirectToProvider()
    {
        $state = Str::random(40);
        session(['oauth_state' => $state]);

        $params = [
            'client_id' => config('services.vatsim.client_id'),
            'redirect_uri' => route('auth.callback'),
            'response_type' => 'code',
            'scope' => 'full_name vatsim_details email',
            'state' => $state,
        ];

        $authUrl = config('services.vatsim.base_url') . '/oauth/authorize?' . http_build_query($params);

        return redirect($authUrl);
    }

    /**
     * Handle OAuth callback from VATSIM
     */
    public function handleProviderCallback(Request $request)
    {
        $code = $request->get('code');

        if (!$code) {
            return redirect('/')->with('error', 'Authentication failed');
        }

        $returnedState = $request->get('state');
        $expectedState = session('oauth_state');
        $request->session()->forget('oauth_state');

        if (!$returnedState || !$expectedState || !hash_equals($expectedState, $returnedState)) {
            return redirect('/')->with('error', 'Invalid OAuth state');
        }

        // Exchange code for access token
        $response = Http::asForm()->post(config('services.vatsim.base_url') . '/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.vatsim.client_id'),
            'client_secret' => config('services.vatsim.client_secret'),
            'redirect_uri' => route('auth.callback'),
            'code' => $code,
        ]);

        if (!$response->successful()) {
            return redirect('/')->with('error', 'Failed to obtain access token');
        }

        $tokenData = $response->json();
        $accessToken = $tokenData['access_token'];

        // Get user data from Handover
        $userResponse = Http::withToken($accessToken)
            ->get(config('services.vatsim.base_url') . '/api/user');

        if (!$userResponse->successful()) {
            \Log::error('Failed to get user data from Handover', [
                'status' => $userResponse->status(),
                'body' => $userResponse->body(),
            ]);
            return redirect('/')->with('error', 'Failed to get user data');
        }

        $userData = $userResponse->json();
        \Log::info('Handover user data received for user: ' . $userData['data']['cid']);

        // Handle both Handover and VATSIM Connect response formats
        $vatsimUser = $userData['data'] ?? $userData;

        // Create or update user
        $user = User::updateOrCreate(
            ['vatsim_cid' => $vatsimUser['cid']],
            [
                'name' => $vatsimUser['personal']['name_full'] ?? $vatsimUser['name'] ?? 'User ' . $vatsimUser['cid'],
                'email' => $vatsimUser['personal']['email'] ?? $vatsimUser['email'] ?? null,
                'vatsim_rating' => $vatsimUser['vatsim']['rating']['short'] ?? $vatsimUser['rating'] ?? null,
            ]
        );

        // Assign default role if user doesn't have any
        if (!$user->hasAnyRole(['admin', 'moderator', 'user'])) {
            $user->assignRole('user');
        }

        Auth::login($user);

        return redirect()->intended('/');
    }

    /**
     * Show development login page (only in local environment)
     */
    public function showDevLogin()
    {
        if (!app()->environment('local')) {
            abort(404);
        }

        $users = User::with('roles')->get();

        return view('dev-login', [
            'users' => $users,
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
