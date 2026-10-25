<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EventFilterRequest;
use App\Http\Resources\Api\V1\EventDetailResource;
use App\Http\Resources\Api\V1\EventResource;
use App\Models\Event;
use App\Models\EventOccurrence;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    /**
     * List published events.
     *
     * Events with a date in the requested range, by that date; without a range, events
     * with a date that hasn't ended. Cancelled events are included with `cancelledAt`.
     */
    public function index(EventFilterRequest $request): AnonymousResourceCollection
    {
        $events = $request->filterEvents(Event::query()->published())
            ->whereHas('occurrences', fn ($query) => $request->filterOccurrences($query))
            ->orderBy($request->filterOccurrences(EventOccurrence::query())
                ->select('starts_at')
                ->whereColumn('event_id', 'events.id')
                ->orderBy('starts_at')
                ->limit(1))
            ->orderBy('id')
            ->with(['fir', 'eventType', 'occurrences'])
            ->paginate($request->perPage())
            ->withQueryString();

        return EventResource::collection($events);
    }

    /**
     * Show a published event, with its positions and their shifts.
     */
    public function show(Event $event): EventDetailResource
    {
        // A Draft is not found, rather than forbidden, so its existence isn't revealed.
        abort_if($event->isDraft(), 404);

        return new EventDetailResource($event->load(['fir', 'eventType', 'occurrences', 'positions.shifts']));
    }
}
