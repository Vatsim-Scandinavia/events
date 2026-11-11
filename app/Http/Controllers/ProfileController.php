<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentSource;
use App\Enums\NameDisplay;
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
                'nameDisplay' => ($user->name_display ?? NameDisplay::FirstName)->value,
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
     * The user's roles grouped by where they hold them: across the Hub first, then each FIR
     * by ICAO. Empty for a Pilot.
     *
     * @return list<array{scope: string, roles: list<array{name: string, automatic: bool}>}>
     */
    private function roleGroups(User $user): array
    {
        return $user->roleAssignments()->with(['role', 'fir'])->get()
            ->sortBy(fn (RoleAssignment $assignment) => [$assignment->fir?->icao ?? '', $assignment->role->name])
            ->groupBy(fn (RoleAssignment $assignment) => match (true) {
                $assignment->fir === null => 'Across the Hub',
                $assignment->fir->name === $assignment->fir->icao => $assignment->fir->icao,
                default => "{$assignment->fir->icao} {$assignment->fir->name}",
            })
            ->map(fn ($assignments, string $scope) => [
                'scope' => $scope,
                'roles' => $assignments->map(fn (RoleAssignment $assignment) => [
                    'name' => $assignment->role->name,
                    'automatic' => $assignment->source === AssignmentSource::Automatic,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
