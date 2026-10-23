<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\EventOccurrence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Publishing and hiding a roster. Its slots are saved with the staffing plan.
 */
class RosterController extends Controller
{
    public function publish(EventOccurrence $occurrence): RedirectResponse
    {
        Gate::authorize('manageStaffing', $occurrence->event);
        $occurrence->publishRoster();

        return Inertia::flash('success', 'The roster is published.')->back();
    }

    public function unpublish(EventOccurrence $occurrence): RedirectResponse
    {
        Gate::authorize('manageStaffing', $occurrence->event);
        $occurrence->unpublishRoster();

        return Inertia::flash('success', 'The roster is hidden again.')->back();
    }
}
