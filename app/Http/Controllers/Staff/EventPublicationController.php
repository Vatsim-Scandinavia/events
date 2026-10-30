<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Event Staff publishing a Draft, so everyone can see it. There's no unpublishing.
 */
class EventPublicationController extends Controller
{
    public function publish(Event $event): RedirectResponse
    {
        Gate::authorize('publish', $event);

        $event->publish();

        Inertia::flash('success', "{$event->name} is published.");

        return redirect("/events/{$event->id}");
    }
}
