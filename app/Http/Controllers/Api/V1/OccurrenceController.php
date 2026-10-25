<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EventFilterRequest;
use App\Http\Resources\Api\V1\OccurrenceDetailResource;
use App\Models\EventOccurrence;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OccurrenceController extends Controller
{
    /**
     * List the dates of published events.
     *
     * Dates in the requested range, soonest first; without a range, dates that haven't ended.
     * Each date carries a summary of its event. Cancelled dates are included with `cancelledAt`.
     */
    public function index(EventFilterRequest $request): AnonymousResourceCollection
    {
        $occurrences = $request->filterOccurrences(EventOccurrence::query())
            ->whereHas('event', fn ($query) => $request->filterEvents($query->published()))
            ->orderBy('starts_at')
            ->orderBy('id')
            ->with(['event.fir', 'event.eventType'])
            ->paginate($request->perPage())
            ->withQueryString();

        return OccurrenceDetailResource::collection($occurrences);
    }

    /**
     * Show a date of a published event, with a summary of its event.
     */
    public function show(EventOccurrence $occurrence): OccurrenceDetailResource
    {
        // A Draft's dates are not found, rather than forbidden, so the Draft isn't revealed.
        abort_if($occurrence->event->isDraft(), 404);

        return new OccurrenceDetailResource($occurrence->load(['event.fir', 'event.eventType']));
    }
}
