<?php

namespace App\Http\Controllers\Admin;

use App\Auth\Controllers;
use App\Http\Controllers\Controller;
use App\Models\ControllerGrant;
use App\Models\Fir;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Makes someone a Controller by hand, Hub-wide or in one FIR, or takes a hand grant away: for
 * visiting controllers, say. Only where the signed-in user manages users; the rules live in
 * App\Auth\Controllers.
 */
class ControllerGrantController extends Controller
{
    public function __construct(private Controllers $controllers) {}

    public function store(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'fir_id' => ['nullable', 'integer', Rule::exists('firs', 'id')],
        ], [
            'fir_id.*' => 'Pick where they’re a Controller.',
        ]);

        $fir = isset($validated['fir_id']) ? Fir::query()->findOrFail($validated['fir_id']) : null;

        $this->controllers->grantByHand($request->user(), $user, $fir);

        Inertia::flash('success', "{$user->name} is now a Controller ".Controllers::place($fir).'.');

        return redirect("/admin/users/{$user->id}");
    }

    public function destroy(Request $request, User $user, ControllerGrant $controllerGrant): RedirectResponse
    {
        $this->controllers->revokeHandGrant($request->user(), $controllerGrant);

        Inertia::flash('success', "{$user->name} is no longer a Controller ".Controllers::place($controllerGrant->fir).' by hand.');

        return redirect("/admin/users/{$user->id}");
    }
}
