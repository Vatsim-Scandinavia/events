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

        $events = Event::with('nextInstance')->get();

        return view('events.index', compact('events'));
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
     * Display the specified resource.
     */
    public function show(Request $request, Event $event)
    {
        // 1. Check if a specific instance was requested in the URL (?instance=105)
        $instanceId = $request->query('instance');
        
        if ($instanceId) {
            $displayInstance = $event->instances()->find($instanceId);
        }

        // 2. Fallback: If no instance ID or ID is invalid, get the next upcoming one
        if (!$displayInstance) {
            $displayInstance = $event->instances()
                ->where('start_time', '>=', now())
                ->orderBy('start_time')
                ->first() ?? $event->instances()->latest('start_time')->first();
        }

        return view('events.show', compact('event', 'displayInstance'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Event $event)
    {
        $this->authorize('update', $event);

        $calendars = Calendar::all();

        return view('events.edit', compact('calendars', 'event'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEventRequest $request, EventService $eventService)
    {
        $this->authorize('create', Event::class);
    
        $data = $request->validated();

        $data['start_date'] = \Carbon\Carbon::parse($request->start_date)->format('Y-m-d H:i:s');
        $data['end_date'] = \Carbon\Carbon::parse($request->end_date)->format('Y-m-d H:i:s');
        $data['user_id'] = auth()->id();

        if ($request->input('event_type') == '0') {
            $data['recurrence_interval'] = null;
            $data['recurrence_unit'] = null;
            $data['recurrence_end_date'] = null;
        } else {
            $data['recurrence_end_date'] = \Carbon\Carbon::parse($request->recurrence_end_date)->format('Y-m-d H:i:s');
        }

        $event = $eventService->createEventWithInstances($data, $request->file('image'));

        // ... Discord ...
        
        return redirect()->route('events.index')
            ->withSuccess('Event created!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEventRequest $request, Event $event, EventService $eventService)
    {
        $this->authorize('update', $event);

        $data = $request->validated();

        // Format dates for the parent DB table
        $data['start_date'] = \Carbon\Carbon::parse($request->start_date)->format('Y-m-d H:i:s');
        $data['end_date'] = \Carbon\Carbon::parse($request->end_date)->format('Y-m-d H:i:s');

        // Handle single event cleanup
        if ($request->input('event_type') == '0') {
            $data['recurrence_interval'] = null;
            $data['recurrence_unit'] = null;
            $data['recurrence_end_date'] = null;
        } else {
            $data['recurrence_end_date'] = \Carbon\Carbon::parse($request->recurrence_end_date)->format('Y-m-d H:i:s');
        }

        $eventService->updateEvent($event, $data, $request->file('image'));

        return redirect()->route('events.index')
            ->withSuccess('Event updated and schedule regenerated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Event $event, EventService $eventService)
    {
        $this->authorize('destroy', $event);

        $eventService->deleteEvent($event);

        return redirect()->route('events.index')
            ->withSuccess('Event and all scheduled instances have been removed.');
    }
}
