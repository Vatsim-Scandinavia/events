<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\OAuthController;
use App\Models\Area;
use App\Models\Group;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    protected $provider;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->provider = new OAuthController();
        // $this->middleware('guest')->except('logout');
    }

    /**
     * Login the user
     * 
     * @param \Illuminate\Http\Request $request request to proccess
     * @return mixed
     */
    public function login(Request $request)
    {
        if(!$request->has('code') || !$request->has('state')) {
            $authURL = $this->provider->getAuthorizationUrl();

            $request->session()->put('oauthstate', $this->provider->getState());

            return redirect()->away($authURL);
        } else if($request->input('state') !== session()->pull('oauthstate')) {
            return redirect()->route('welcome')->withErrors("Something went wrong, please try again (state mismatch).");
        } else {
            return $this->verifyLogin($request);
        }
    }

    /**
     * Verify the login of the user's request before proceeding
     * 
     * @param \Illuminate\Http\Request $request request to proccess
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function verifyLogin(Request $request)
    {
        try {
            $accessToken = $this->provider->getAccessToken('authorization_code', [
                'code' => $request->input('code')
            ]);
        } catch (IdentityProviderException $e) {
            return redirect()->route('welcome')->withError("Authentication error: ".$e->getMessage());
        }

        $resourceOwner = json_decode(json_encode($this->provider->getResourceOwner($accessToken)->toArray()));

        if(!isset($resourceOwner)) {
            return redirect()->route('welcome')->withErrors("You did not grant all data which is required to use this service.");
        }

        $account = $this->completeLogin($resourceOwner, $accessToken);

        auth()->login($account, false);

        try {

            $client = new \GuzzleHttp\Client();  // Initalize client

            $response = $client->request('GET', Config::get('custom.cc_url') . '/api/roles', [
                'headers' => [
                    'Authorization' => 'Bearer ' . Config::get('custom.cc_api_secret'),
                    'Accept' => 'application/json',
                ]
            ]); // Make request

            $data = json_decode($response->getBody()->getContents());

            $existingUserIds = []; // Track existing user IDs

            $roles = ['admins' => 1, 'moderators' => 2]; // Map roles to group IDs

            foreach ($roles as $role => $groupId) {
                foreach ($data->data->$role as $key => $item) {
                    $existingUserIds[] = $item->id; // Add user ID to the existing user IDs array
        
                    $user = User::findOrFail($item->id);
        
                    foreach ($item->fir as $fir) {
                        $area = Area::where('name', $fir)->first();
                        $user->groups()->syncWithoutDetaching([$groupId => ['area_id' => $area->id]]);
                    }
        
                    continue;
                }
            }

            // Remove the group if the user's data is not in the API response
            foreach (\Auth::user()->groups as $group) {
                if (!in_array($group->pivot->user_id, $existingUserIds)) {
                    \Auth::user()->groups()->detach($group);
                }
            }
        } catch(\GuzzleHttp\Exception\ClientException $e) {
            return redirect()->route('welcome')->withError("Authentication error: ".$e->getMessage());
        }


        return redirect()->intended(route('dashboard'))->withSuccess('Login Successful');
    }

    /**
     * Complete the login by creating or updating the existing account and last login timestamp
     * 
     * @param mixed $resourceOwner
     * @param mixed $token
     * @return \App\Models\User User's account data
     */
    protected function completeLogin($resourceOwner, $token)
    {
        $account = User::updateOrCreate(
            ['id' => $resourceOwner->data->cid],
            ['last_login' => Carbon::now()],
        );

        $account->save();

        return $account;
    }

    /**
     * Log out he user and redirect to front page
     * 
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout()
    {
        auth()->logout();

        return redirect()->route('welcome')->withSuccess('You have been successfully logged out.');
    }
}
