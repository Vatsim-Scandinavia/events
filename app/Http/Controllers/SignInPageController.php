<?php

namespace App\Http\Controllers;

use App\Auth\SignIn;
use App\Enums\SignInProvider;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The sign-in page: one button for the configured Sign-in Provider, and with APP_ENV=local a
 * one-click sign-in for each of the VATSIM Connect sandbox's test members, the CIDs the seeder uses.
 *
 * APP_ENV=local is the only guard, as in Control Center: outside it the CIDs aren't sent and the
 * dev route is a 404, whatever the request says. A server must never run with APP_ENV=local.
 */
class SignInPageController extends Controller
{
    /** The sandbox's test members, CIDs 10000001 to 10000010. */
    private const array DEV_CIDS = [10000001, 10000002, 10000003, 10000004, 10000005, 10000006, 10000007, 10000008, 10000009, 10000010];

    /**
     * A sign-in link passes the page it was on as `return`, which the buttons carry on, so people
     * land back there afterwards.
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
            'devCids' => app()->environment('local') ? self::DEV_CIDS : null,
        ]);
    }

    /**
     * Sign in as a sandbox test member, creating their Account the first time.
     */
    public function dev(Request $request, SignIn $signIn, int $cid): RedirectResponse
    {
        abort_unless(app()->environment('local') && in_array($cid, self::DEV_CIDS, true), 404);

        $returnTo = $request->input('return');
        if (is_string($returnTo) && SignIn::isLocalPath($returnTo)) {
            redirect()->setIntendedUrl(url($returnTo));
        }

        return $signIn->signInAs($request, User::query()->firstOrCreate(['vatsim_cid' => $cid], ['name' => "VATSIM {$cid}"]));
    }
}
