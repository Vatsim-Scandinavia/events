<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Event;
use App\Http\Resources\EventResource;

class EventApiController extends Controller
{
    /**
     * Display a listing of events with optional filters.
     * 
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
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

        $events = $query->orderBy('start_datetime')->paginate(10);

        return EventResource::collection($events);
    }

    /**
     * Display the specified event.
     * 
     * @param int $id
     * @return EventResource
     */
    public function show($id)
    {
        $event = Event::with(['calendar', 'staffings.positions.bookedBy'])->findOrFail($id);
        return new EventResource($event);
    }

    /**
     * Remove the specified event.
     * (Not implemented in API controller)
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }
}
