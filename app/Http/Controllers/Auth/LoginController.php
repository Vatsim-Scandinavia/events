<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\OAuthController;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
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
            return redirect()->back()->withErrors("Something went wrong, please try again (state mismatch).");
            // return redirect()->route('welcome')->withErrors("Something went wrong, please try again (state mismatch).");
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
            return redirect()->back()->withError("Authentication error: ".$e->getMessage());
        }

        $resourceOwner = json_decode(json_encode($this->provider->getResourceOwner($accessToken)->toArray()));

        if(!isset($resourceOwner)) {
            // return redirect()->route('welcome')->withErrors("You did not grant all data which is required to use this service.");
            return redirect()->back()->withErrors("You did not grant all data which is required to use this service.");
        }

        $account = $this->completeLogin($resourceOwner, $accessToken);

        auth()->login($account, false);

        $authLevel = "User";

        // if(\Auth::user()->groups->count() > 0) {
        //     $authLevel = User::find(\Auth::user()->id)->groups->sortBy('id')->first()->name;
        // }

        return redirect()->intended(route('home'))->withSuccess('Login Successful');
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
            ['id' => $resourceOwner->data->id],
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
