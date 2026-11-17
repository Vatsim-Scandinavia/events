<?php

namespace App\Http\Controllers;

use App\Auth\Controllers;
use App\Auth\Roles;
use App\Enums\ControllerGrantOrigin;
use App\Enums\NameDisplay;
use App\Models\ControllerGrant;
use App\Models\RoleAssignment;
use App\Models\User;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Whoever is signed in: who they are on VATSIM, the roles they hold, and their preferences.
 * The preferences are how their name is shown to everyone else, and the time zone event times
 * are shown in.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            'details' => [
                'name' => $user->name,
                'cid' => $user->vatsim_cid,
                'rating' => $user->rating?->label(),
                'subdivision' => $user->subdivision,
            ],
            'roleGroups' => $this->roleGroups($user),
            'settings' => [
                'nameDisplay' => $user->name_display->value,
                'timezone' => $user->displayTimezone(),
            ],
            // Each choice as the user would be shown with it.
            'nameDisplays' => collect(NameDisplay::cases())->map(fn (NameDisplay $display) => [
                'value' => $display->value,
                'label' => $display->label(),
                'example' => $user->displayNameWithCid($display),
            ])->all(),
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name_display' => ['required', Rule::enum(NameDisplay::class)],
            'timezone' => ['required', 'string', 'timezone:all'],
        ], [
            'name_display.*' => 'Pick how your name is shown.',
            'timezone.timezone' => 'Pick a time zone from the list.',
        ]);

        // UTC is the default, so it is stored as no preference at all.
        $request->user()->update([
            'name_display' => $validated['name_display'],
            'timezone' => $validated['timezone'] === 'UTC' ? null : $validated['timezone'],
        ]);

        return Inertia::flash('success', 'Your settings are saved.')->back();
    }

    /**
     * The user's roles, and where they're a Controller, grouped by where they hold them: across
     * the Hub first, then each FIR by ICAO. Being a Controller counts as automatic when the
     * Controller Rule or the Controller Source decided it. Empty for a Pilot.
     *
     * @return list<array{scope: string, roles: list<array{name: string, automatic: bool}>}>
     */
    private function roleGroups(User $user): array
    {
        $held = collect([
            ...$user->roleAssignments()->with(['role', 'fir'])->get()
                ->map(fn (RoleAssignment $assignment) => ['name' => $assignment->role->name, 'fir' => $assignment->fir, 'automatic' => false]),
            ...$user->controllerGrants()->with('fir')->get()
                ->map(fn (ControllerGrant $grant) => ['name' => Roles::CONTROLLER_LABEL, 'fir' => $grant->fir, 'automatic' => $grant->origin === ControllerGrantOrigin::Source]),
            ...(app(Controllers::class)->ruleApplies($user) ? [['name' => Roles::CONTROLLER_LABEL, 'fir' => null, 'automatic' => true]] : []),
        ]);

        return $held
            ->sortBy(fn (array $role) => [$role['fir']?->icao ?? '', $role['name']])
            ->groupBy(fn (array $role) => match (true) {
                $role['fir'] === null => 'Across the Hub',
                $role['fir']->name === $role['fir']->icao => $role['fir']->icao,
                default => "{$role['fir']->icao} {$role['fir']->name}",
            })
            ->map(fn ($roles, string $scope) => [
                'scope' => $scope,
                'roles' => $roles->unique('name')->map(fn (array $role) => ['name' => $role['name'], 'automatic' => $role['automatic']])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
