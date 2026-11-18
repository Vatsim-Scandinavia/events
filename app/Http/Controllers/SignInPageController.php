<?php

namespace App\Http\Controllers;

use App\Auth\DevSignIn;
use App\Auth\SignIn;
use App\Enums\SignInProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The sign-in page: one button for the configured Sign-in Provider, and with APP_ENV=local the dev
 * links for the sandbox's test members.
 */
class SignInPageController extends Controller
{
    /**
     * A sign-in link passes the page it was on as `return`, which the button and the dev links
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
            'returnTo' => is_string($returnTo) && SignIn::isLocalPath($returnTo) ? $returnTo : null,
            'devLinks' => DevSignIn::isAvailable() ? DevSignIn::links() : null,
        ]);
    }

    /**
     * Sign in as a sandbox test member, locally only.
     */
    public function dev(Request $request, SignIn $signIn, int $cid): RedirectResponse
    {
        abort_unless(DevSignIn::isAvailable() && DevSignIn::isSandboxCid($cid), 404);

        $returnTo = $request->input('return');
        if (is_string($returnTo) && SignIn::isLocalPath($returnTo)) {
            redirect()->setIntendedUrl(url($returnTo));
        }

        return $signIn->signInAs($request, DevSignIn::account($cid));
    }
}
