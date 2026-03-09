<?php

namespace App\Http\Controllers;

use App\Actions\CreateEvent;
use App\Actions\DeleteEvent;
use App\Actions\UpdateEvent;
use App\Models\Event;
use App\Models\Calendar;
use App\Services\BannerUploadService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\EventService;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;

class EventController extends Controller
{
    public function __construct(
        protected BannerUploadService $bannerUploadService
    ) {}

    /**
     * Display a listing of events
     * 
     * @param Request $request
     * @param EventService $eventService
     * @return InertiaResponse
     */
    public function index(Request $request, EventService $eventService)
    {
        $events = Event::with(['calendar', 'creator'])
            ->when($request->search, function ($query, $search) {
                $query->where('title', 'like', "%{$search}%");
            })
            ->when($request->calendar_id, function ($query, $id) {
                $query->where('calendar_id', $id);
            })
            ->orderBy('start_datetime', 'desc')
            ->paginate(12)
            ->withQueryString();

        $events->getCollection()->transform(fn($event) => $eventService->getEventSummary($event));

        return Inertia::render('Events/Index', [
            'events' => $events,
            'filters' => $request->only(['search', 'calendar_id']),
        ]);
    }

    /**
     * Show the form for creating a new event
     * 
     * @param Request $request
     * @return InertiaResponse
     */
    public function create(Request $request)
    {
        $this->authorize('create', Event::class);

        $calendars = Calendar::visibleTo($request->user())->get();

        // Get calendar_id from query parameter if provided
        $preselectedCalendarId = $request->query('calendar_id');

        return Inertia::render('Events/Create', [
            'calendars' => $calendars,
            'preselectedCalendarId' => $preselectedCalendarId,
        ]);
    }

    /**
     * Store a newly created event
     * 
     * @param StoreEventRequest $request
     * @param CreateEvent $createEvent
     * @return RedirectResponse
     */
    public function store(StoreEventRequest $request, CreateEvent $createEvent)
    {
        $event = $createEvent($request->validated(), auth()->user());

        return redirect()->route('events.show', $event)
            ->with('success', 'Event created successfully.');
    }

    /**
     * Display the specified event
     * 
     * @param Event $event
     * @param EventService $eventService
     * @return InertiaResponse
     */
    public function show(Event $event, EventService $eventService)
    {
        $this->authorize('view', $event);

        $details = $eventService->getEventDetails($event, true);

        return Inertia::render('Events/Show', [
            'event'     => $details,
            'instances' => $details['instances'],
            'bannerUrl' => $details['banner_url'],
            'nextStart' => $details['display_datetime'],
            'nextEnd'   => $details['next_active_end'],
        ]);
    }

    /**
     * Show the form for editing the event
     * 
     * @param Request $request
     * @param Event $event
     * @param EventService $eventService
     * @return InertiaResponse
     */
    public function edit(Request $request, Event $event, EventService $eventService)
    {
        $this->authorize('update', $event);

        $calendars = Calendar::visibleTo($request->user())->get();

        $details = $eventService->getEventDetails($event);

        return Inertia::render('Events/Edit', [
            'event'     => $event,
            'calendars' => $calendars,
            'bannerUrl' => $details['banner_url'],
        ]);
    }

    /**
     * Update the specified event
     * 
     * @param UpdateEventRequest $request
     * @param Event $event
     * @param UpdateEvent $updateEvent
     * @return RedirectResponse
     */
    public function update(UpdateEventRequest $request, Event $event, UpdateEvent $updateEvent)
    {
        $this->authorize('update', $event);

        $event = $updateEvent($event, $request->validated(), auth()->user(), $request->file('banner'));

        return redirect()->route('events.show', $event)
            ->with('success', 'Event updated successfully.');
    }

    /**
     * Remove the specified event
     * 
     * @param Event $event
     * @param DeleteEvent $deleteEvent
     * @return RedirectResponse
     */
    public function destroy(Event $event, DeleteEvent $deleteEvent)
    {
        $this->authorize('delete', $event);

        $deleteEvent($event, auth()->user());

        return redirect()->route('events.index')
            ->with('success', 'Event deleted successfully.');
    }

    /**
     * Cancel a specific occurrence of a recurring event
     * 
     * @param Request $request
     * @param Event $event
     * @param EventService $eventService
     * @return RedirectResponse
     */
    public function cancelOccurrence(Request $request, Event $event, EventService $eventService)
    {
        $this->authorize('update', $event);

        if (!$event->recurrence_rule) {
            return back()->withErrors(['error' => 'Only recurring events can have occurrences cancelled.']);
        }

        $validated = $request->validate([
            'occurrence_date' => 'required|date',
        ]);

        $eventService->toggleOccurrence($event, $validated['occurrence_date'], true);

        return back()->with('success', 'Occurrence cancelled successfully.');
    }

    /**
     * Uncancel a specific occurrence of a recurring event
     * 
     * @param Request $request
     * @param Event $event
     * @param EventService $eventService
     * @return RedirectResponse
     */
    public function uncancelOccurrence(Request $request, Event $event, EventService $eventService)
    {
        $this->authorize('update', $event);

        if (!$event->recurrence_rule) {
            return back()->withErrors(['error' => 'Only recurring events can have occurrences uncancelled.']);
        }

        $validated = $request->validate([
            'occurrence_date' => 'required|date',
        ]);

        $eventService->toggleOccurrence($event, $validated['occurrence_date'], false);

        return back()->with('success', 'Occurrence uncancelled successfully.');
    }

    /**
     * Show page for managing occurrences of a recurring event
     * 
     * @param Event $event
     * @param EventService $eventService
     * @return InertiaResponse
     */
    public function manageOccurrences(Event $event, EventService $eventService)
    {
        $this->authorize('update', $event);

        if (!$event->recurrence_rule) {
            return redirect()->route('events.show', $event)
                ->withErrors(['error' => 'Only recurring events have occurrences to manage.']);
        }

        $data = $eventService->getManagementData($event);

        return Inertia::render('Events/ManageOccurrences', [
            'event'       => $data['event'],
            'occurrences' => $data['occurrences'],
        ]);
    }
}
