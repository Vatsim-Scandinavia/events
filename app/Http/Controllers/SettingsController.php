<?php

namespace App\Http\Controllers;

use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Personal preferences for whoever is signed in. For now, the time zone event times are
 * shown in.
 */
class SettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Settings/Edit', [
            'settings' => [
                'timezone' => $request->user()->displayTimezone(),
            ],
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'timezone' => ['required', 'string', 'timezone:all'],
        ], [
            'timezone.timezone' => 'Pick a time zone from the list.',
        ]);

        // UTC is the default, so it is stored as no preference at all.
        $request->user()->update([
            'timezone' => $validated['timezone'] === 'UTC' ? null : $validated['timezone'],
        ]);

        return Inertia::flash('success', 'Your settings are saved.')->back();
    }
}
