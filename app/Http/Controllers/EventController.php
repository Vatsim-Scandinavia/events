<?php

namespace App\Http\Controllers;

use App\Actions\CreateEvent;
use App\Actions\DeleteEvent;
use App\Actions\UpdateEvent;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Http\Resources\CalendarResource;
use App\Http\Resources\EventResource;
use App\Models\Calendar;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $canManage = $request->user()?->can('manage events');

        $query = Event::with(['calendar', 'occurrences', 'futureOccurrences'])
            ->withMin(
                [
                    'occurrences as next_occurrence_at' => fn($q) => $q
                        ->where('start_time', '>=', now())
                        ->where('status', '!=', 'cancelled')
                ],
                'start_time'
            )
            ->whereHas(
                'occurrences',
                fn($q) => $q
                    ->where('start_time', '>=', now())
                    ->where('status', '!=', 'cancelled')
            )
            ->orderBy('next_occurrence_at');

        if (!$canManage) {
            $query->where('status', 'published');
        }

        return Inertia::render('Events/Index', [
            'events' => EventResource::collection($query->paginate(20)),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Event::class);

        return Inertia::render('Events/Create', [
            'calendars' => CalendarResource::collection(Calendar::all()),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEventRequest $request, CreateEvent $createEvent)
    {
        $validated = $request->validated();
        $banner    = $request->file('banner');

        $timezone = $validated['timezone'];
        $start    = Carbon::createFromFormat('Y-m-d\TH:i:s', $validated['start_datetime'], $timezone)->utc();
        $end      = Carbon::createFromFormat('Y-m-d\TH:i:s', $validated['end_datetime'], $timezone)->utc();
        unset($validated['start_datetime'], $validated['end_datetime']);

        $event = $createEvent($validated, $request->user(), $start, $end, $banner);

        return redirect()->route('events.show', $event);
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event)
    {
        $this->authorize('view', $event);

        $event->load(['calendar', 'occurrences', 'futureOccurrences', 'staffing.sections.positions']);

        return Inertia::render('Events/Show', [
            'event' => new EventResource($event),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Event $event)
    {
        $this->authorize('update', $event);

        $event->load(['calendar', 'occurrences', 'futureOccurrences', 'staffing']);

        return Inertia::render('Events/Edit', [
            'event' => new EventResource($event),
            'calendars' => CalendarResource::collection(Calendar::all()),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEventRequest $request, Event $event, UpdateEvent $updateEvent)
    {
        $this->authorize('update', $event);

        $validated = $request->validated();
        $banner    = $request->file('banner');

        $timezone = $validated['timezone'] ?? $event->timezone ?? 'UTC';
        $start    = Carbon::createFromFormat('Y-m-d\TH:i:s', $validated['start_datetime'], $timezone)->utc();
        $end      = Carbon::createFromFormat('Y-m-d\TH:i:s', $validated['end_datetime'], $timezone)->utc();
        unset($validated['start_datetime'], $validated['end_datetime']);

        $updateEvent($event, $validated, $request->user(), $start, $end, $banner);

        return redirect()->route('events.show', $event);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Event $event, DeleteEvent $deleteEvent)
    {
        $this->authorize('delete', $event);

        $deleteEvent($event, request()->user());

        return redirect()->route('events.index');
    }

    /**
     * Show the occurrences management page for a recurring event.
     */
    public function manageOccurrences(Event $event)
    {
        $this->authorize('update', $event);

        $occurrences = $event->occurrences()
            ->orderBy('start_time')
            ->get()
            ->map(fn($occ) => [
                'start'     => $occ->start_time,
                'end'       => $occ->end_time,
                'cancelled' => $occ->status === 'cancelled',
            ]);

        return Inertia::render('Events/ManageOccurrences', [
            'event'       => new EventResource($event),
            'occurrences' => $occurrences,
        ]);
    }

    /**
     * Cancel a single occurrence of an event.
     */
    public function cancelOccurrence(Request $request, Event $event)
    {
        $request->validate(['occurrence_date' => 'required|date']);

        $occurrence = $event->occurrences()
            ->where('start_time', Carbon::parse($request->occurrence_date))
            ->firstOrFail();

        $this->authorize('cancel', $occurrence);

        $occurrence->update(['status' => 'cancelled']);

        return redirect()->back();
    }

    /**
     * Restore a cancelled occurrence of an event.
     */
    public function uncancelOccurrence(Request $request, Event $event)
    {
        $request->validate(['occurrence_date' => 'required|date']);

        $occurrence = $event->occurrences()
            ->where('start_time', Carbon::parse($request->occurrence_date))
            ->firstOrFail();

        $this->authorize('restore', $occurrence);

        $occurrence->update(['status' => 'scheduled']);

        return redirect()->back();
    }
}
