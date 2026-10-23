<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\DiscordAnnouncements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Event Staff publishing a Draft, so everyone can see it, and announcing it on Discord the
 * first time. There's no unpublishing.
 */
class EventPublicationController extends Controller
{
    public function publish(Event $event, DiscordAnnouncements $announcements): RedirectResponse
    {
        Gate::authorize('publish', $event);

        if ($event->publish()) {
            $announcements->eventPublished($event);
        }

        Inertia::flash('success', "{$event->name} is published.");

        return redirect("/events/{$event->id}");
    }
}
