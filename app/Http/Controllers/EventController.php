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

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('index', Event::class);

        $events = Event::upcoming()->with('nextInstance', 'calendar', 'user')->get();

        return view('events.index', compact('events'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event)
    {
        $this->authorize('view', $event);

        // Get the instance based on the ?instance parameter
        $displayInstance = $event->getDisplayInstance();

        if ($displayInstance) {
            $displayInstance->load(['staffing.positions.user']);
        }

        return view('events.show', compact('event', 'displayInstance'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEventRequest $request, EventService $eventService)
    {
        $this->authorize('create', Event::class);
    
        $event = $eventService->createEventWithInstances(
            $request->validated(), 
            $request->file('image')
            
        );

        return redirect()->route('events.index')->withSuccess('Event created!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEventRequest $request, Event $event, EventService $eventService)
    {
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
