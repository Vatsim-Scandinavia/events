<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use App\Http\Resources\EventResource;

/**
 * Event API Controller
 */
class EventController extends Controller
{
    /**
     * Get all events
     */
    public function index(Request $request)
    {
        $query = Event::with(['calendar', 'staffings.positions.bookedBy']);

        if ($request->boolean('upcoming', true)) {
            $query->where('end_datetime', '>=', now());
        }

        if ($request->boolean('staffing', false)) {
            $query->whereHas('staffings');
        }

        $events = $query->orderBy('start_datetime')->get();

        return EventResource::collection($events);
    }

    /**
     * Get single event by ID
     */
    public function show($id)
    {
        $event = Event::with(['calendar', 'staffings.positions.bookedBy'])->findOrFail($id);

        return new EventResource($event);
    }
}
