<?php

namespace App\Http\Controllers;

use App\Models\EventOccurrence;
use App\Staffing\StaffingInterests;
use App\Staffing\ZuluTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StaffingInterestController extends Controller
{
    public function update(Request $request, EventOccurrence $occurrence, StaffingInterests $interests): RedirectResponse
    {
        $validated = $request->validate([
            'positions' => ['required', 'array', 'min:1'],
            'positions.*' => ['integer'],
            'windows' => ['nullable', 'array', 'max:6'],
            'windows.*.from' => ['required', 'regex:'.ZuluTime::PATTERN],
            'windows.*.until' => ['required', 'regex:'.ZuluTime::PATTERN],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'positions.required' => 'Pick at least one position.',
            'windows.max' => 'Add at most 6 availability windows.',
            'windows.*.from.required' => 'Enter a start time.',
            'windows.*.until.required' => 'Enter an end time.',
            'windows.*.from.regex' => 'Use HH:MM.',
            'windows.*.until.regex' => 'Use HH:MM.',
        ]);

        $interests->submit(
            $request->user(),
            $occurrence,
            array_map('intval', $validated['positions']),
            array_values(array_map(fn (array $window) => ['from' => $window['from'], 'until' => $window['until']], $validated['windows'] ?? [])),
            $validated['notes'] ?? null,
        );

        return Inertia::flash('success', 'Your interest has been saved.')->back();
    }

    public function destroy(Request $request, EventOccurrence $occurrence, StaffingInterests $interests): RedirectResponse
    {
        $interests->withdraw($request->user(), $occurrence);

        return Inertia::flash('success', 'Your interest has been withdrawn.')->back();
    }
}
