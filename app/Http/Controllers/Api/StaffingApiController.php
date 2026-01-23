<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StaffingResource;
use App\Models\Event;
use App\Services\RecurringEventService;
use Illuminate\Http\Request;

class StaffingApiController extends Controller
{
    protected RecurringEventService $recurringEventService;

    public function __construct(RecurringEventService $recurringEventService)
    {
        $this->recurringEventService = $recurringEventService;
    }

    /**
     * Display a listing of events with staffing.
     * 
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        $query = Event::with(['calendar', 'staffings.positions.bookedBy', 'staffings.event'])
            ->whereHas('staffings');

        if ($request->boolean('upcoming', true)) {
            $query->where('end_datetime', '>=', now());
        }

        $events = $query->orderBy('start_datetime')->get();

        $transformedEvents = $events->map(function ($event) {
            // For each event, determine the target occurrence date
            $targetOccurrenceDate = $event->start_datetime;

            if ($event->isRecurring()) {
                $instances = $this->recurringEventService->generateInstances(
                    $event->recurrence_rule,
                    $event->start_datetime,
                    now()->addMonths(3),
                    10,
                    $event->cancelled_occurrences ?? []
                );

                $nextOccurrence = collect($instances)->first(fn($instance) => $instance['start']->isFuture());
                $targetOccurrenceDate = $nextOccurrence ? $nextOccurrence['start'] : $event->start_datetime;
            }

            return new StaffingResource($event, $targetOccurrenceDate);
        });

        return StaffingResource::collection($transformedEvents);
    }

    /**
     * Display staffing details for a specific event.
     * 
     * @param int $id
     * @return StaffingResource
     */
    public function show(int $id)
    {
        $event = Event::with(['calendar', 'staffings.positions.bookedBy', 'staffings.event'])->findOrFail($id);

        // Calculate target occurrence date for position times
        $targetOccurrenceDate = null;
        if ($event->isRecurring()) {
            $instances = $this->recurringEventService->generateInstances(
                $event->recurrence_rule,
                $event->start_datetime,
                now()->addMonths(3),
                10,
                $event->cancelled_occurrences ?? []
            );

            $nextOccurrence = collect($instances)->first(fn($instance) => $instance['start']->isFuture());
            $targetOccurrenceDate = $nextOccurrence ? $nextOccurrence['start'] : $event->start_datetime;
        } else {
            $targetOccurrenceDate = $event->start_datetime;
        }

        return new StaffingResource($event, $targetOccurrenceDate);
    }
}
