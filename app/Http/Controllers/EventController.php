<?php

namespace App\Http\Controllers;

use App\Helpers\EventHelper;
use App\Models\Calendar;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Services\EventService;

/**
 * Event Controller
 *
 * Handles CRUD operations for events including recurring event series.
 * Events can be single occurrences or recurring with multiple instances.
 */
class EventController extends Controller
{
    /**
     * Display a paginated list of upcoming events.
     *
     * Only shows events with at least one future instance.
     * Requires moderator permission or above.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $this->authorize('index', Event::class);

        $events = Event::upcoming()->with('nextInstance', 'calendar', 'user')->get();

        return view('events.index', compact('events'));
    }

    /**
     * Display a specific event with its details and staffing information.
     *
     * Accepts optional ?instance=X query parameter to show a specific occurrence.
     * Falls back to showing the next upcoming instance.
     *
     * @param Event $event
     * @return \Illuminate\View\View
     */
    public function show(Event $event)
    {
        $this->authorize('view', $event);

        $displayInstance = $event->getDisplayInstance();

        if ($displayInstance) {
            $displayInstance->load(['staffing.positions']);
        }

        return view('events.show', compact('event', 'displayInstance'));
    }

    /**
     * Store a newly created event with instances.
     *
     * Creates the event and automatically generates instances based on:
     * - Single event: one instance
     * - Recurring: multiple instances per recurrence rules
     *
     * @param StoreEventRequest $request Validated event data
     * @param EventService $eventService
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(StoreEventRequest $request, EventService $eventService)
    {
        $this->authorize('create', Event::class);
    
        $event = $eventService->createEventWithInstances(
            $request->validated(), 
            $request->file('image')
            
        );

        return redirect()->route('events.show', $event)->withSuccess("Event '{$event->title}' created!");
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEventRequest $request, Event $event, EventService $eventService)
    {
        $this->authorize('update', $event);

        $eventService->updateEvent($event, $request->validated(), $request->file('image'));

        return redirect()->route('events.index')->withSuccess('Event and/or series updated!');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Event::class);

        $calendars = Calendar::all();

        return view('events.create', compact('calendars'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Event $event)
    {
        $this->authorize('update', $event);

        $event->load('instances');
        $calendars = Calendar::all();

        return view('events.edit', compact('event', 'calendars'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Event $event, EventService $eventService)
    {
        $this->authorize('destroy', $event);

        $eventService->deleteEvent($event);

        return redirect()->route('events.index')->withSuccess('Event removed.');
    }
}
