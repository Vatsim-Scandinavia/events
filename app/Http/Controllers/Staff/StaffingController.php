<?php

namespace App\Http\Controllers\Staff;

use App\Enums\StaffingMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Staffing\StaffingModes;
use App\Staffing\StaffingPlan;
use App\Staffing\StaffingPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Event Staff's page for setting up and running staffing on an event.
 */
class StaffingController extends Controller
{
    public function edit(Request $request, Event $event, StaffingPresenter $staffing): Response
    {
        Gate::authorize('manageStaffing', $event);
        $occurrence = $this->occurrenceFor($request, $event);

        return Inertia::render('Events/Staffing', [
            'event' => (new EventResource($event))->resolve(),
            ...$staffing->forStaff($event, $occurrence, $request->user()),
        ]);
    }

    /**
     * Changing the staffing mode clears the old mode's shifts, bookings, interest and roster.
     */
    public function update(Request $request, Event $event, StaffingModes $modes): RedirectResponse
    {
        Gate::authorize('manageStaffing', $event);

        $validated = $request->validate([
            'mode' => ['required', Rule::enum(StaffingMode::class)],
            'interest_opens_hours' => ['required', 'integer', 'min:1', 'max:2160'],
            'interest_closes_hours' => ['required', 'integer', 'min:0', 'lt:interest_opens_hours'],
        ], [
            'interest_closes_hours.lt' => 'Interest must close after it opens.',
        ]);

        $event->update([
            'interest_opens_hours' => $validated['interest_opens_hours'],
            'interest_closes_hours' => $validated['interest_closes_hours'],
        ]);
        $modes->switch($event, StaffingMode::from($validated['mode']));

        return Inertia::flash('success', 'Staffing settings saved.')->back();
    }

    /**
     * Save the positions plus their shifts or the roster, as planned on the staffing page.
     */
    public function plan(Request $request, Event $event, StaffingPlan $plan): RedirectResponse
    {
        Gate::authorize('manageStaffing', $event);

        $validated = $request->validate([
            'occurrence_id' => ['required', 'integer'],
            'positions' => ['present', 'array'],
            'positions.*.key' => ['required', 'integer', 'distinct'],
            'positions.*.callsign' => ['required', 'string', 'max:20'],
            'positions.*.frequency' => ['nullable', 'string', 'max:10'],
            'shifts' => ['sometimes', 'array'],
            'shifts.*.id' => ['nullable', 'integer'],
            'shifts.*.position' => ['required', 'integer'],
            'shifts.*.start' => ['required', 'integer'],
            'shifts.*.end' => ['required', 'integer'],
            'roster' => ['sometimes', 'array'],
            'roster.*.id' => ['nullable', 'integer'],
            'roster.*.position' => ['required', 'integer'],
            'roster.*.user_id' => ['required', 'integer'],
            'roster.*.start' => ['required', 'integer'],
            'roster.*.end' => ['required', 'integer'],
            'publish' => ['sometimes', 'boolean'],
        ]);

        $occurrence = $event->occurrences()->findOrFail($validated['occurrence_id']);
        $plan->save($event, $occurrence, $validated);

        return Inertia::flash('success', ($validated['publish'] ?? false) ? 'Saved and published.' : 'Changes saved.')->back();
    }

    /**
     * The occurrence picked with ?occurrence=, or the event's current one.
     */
    private function occurrenceFor(Request $request, Event $event): EventOccurrence
    {
        if ($request->filled('occurrence')) {
            return $event->occurrences()->whereKey($request->integer('occurrence'))->firstOrFail();
        }

        return $event->currentOccurrence() ?? abort(404);
    }
}
