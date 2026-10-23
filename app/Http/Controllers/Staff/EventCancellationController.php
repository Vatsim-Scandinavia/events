<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Services\DisplayTime;
use App\Services\EventCancellations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Event Staff cancelling and restoring a whole event or one of its dates.
 */
class EventCancellationController extends Controller
{
    public function cancelEvent(Event $event, EventCancellations $cancellations): RedirectResponse
    {
        Gate::authorize('cancel', $event);
        $cancellations->cancelEvent($event);

        return Inertia::flash('success', "{$event->name} is cancelled.")->back();
    }

    public function restoreEvent(Event $event, EventCancellations $cancellations): RedirectResponse
    {
        Gate::authorize('cancel', $event);
        $cancellations->restoreEvent($event);

        return Inertia::flash('success', "{$event->name} is back on.")->back();
    }

    public function cancelOccurrence(EventOccurrence $occurrence, EventCancellations $cancellations, DisplayTime $display): RedirectResponse
    {
        Gate::authorize('cancel', $occurrence->event);
        $cancellations->cancelOccurrence($occurrence);

        return Inertia::flash('success', $display->date($occurrence->starts_at).' is cancelled.')->back();
    }

    public function restoreOccurrence(EventOccurrence $occurrence, EventCancellations $cancellations, DisplayTime $display): RedirectResponse
    {
        Gate::authorize('cancel', $occurrence->event);
        $cancellations->restoreOccurrence($occurrence);

        return Inertia::flash('success', $display->date($occurrence->starts_at).' is back on.')->back();
    }
}
