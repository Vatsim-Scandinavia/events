<?php

namespace App\Http\Controllers;

use App\Auth\DevLogin;
use App\Auth\Roles;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A stand-in for VATSIM Connect on local installs: pick any user and become them.
 */
class DevLoginController extends Controller
{
    public function show(): Response
    {
        abort_unless(DevLogin::enabled(), 404);

        return Inertia::render('Auth/DevLogin', [
            // People with roles first, so the interesting accounts are at the top.
            'users' => User::query()
                ->with('roleAssignments.role', 'roleAssignments.fir')
                ->withCount('roleAssignments')
                ->orderByDesc('role_assignments_count')
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'cid' => $user->vatsim_cid,
                    'rating' => $user->rating?->label(),
                    'roles' => Roles::labelsFor($user),
                ]),
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        abort_unless(DevLogin::enabled(), 404);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }
}
