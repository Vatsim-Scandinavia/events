<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Calendar;
use App\Services\BannerUploadService;
use App\Services\RecurringEventService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventController extends Controller
{
    public function __construct(
        protected RecurringEventService $recurringEventService,
        protected BannerUploadService $bannerUploadService
    ) {}

    /**
     * Display a listing of events
     */
    public function index(Request $request)
    {
        $query = Event::with(['calendar', 'creator', 'staffings'])
            ->whereHas('calendar', function ($q) use ($request) {
                $q->visibleTo($request->user());
            });

        if ($request->has('calendar_id')) {
            $query->where('calendar_id', $request->calendar_id);
        }

        if ($request->has('upcoming')) {
            $query->upcoming();
        }

        $events = $query->orderBy('start_datetime')->paginate(5);

        // Calculate next occurrence for recurring events
        $events->getCollection()->transform(function ($event) {
            // For recurring events, find the next occurrence
            if ($event->recurrence_rule) {
                $instances = $this->recurringEventService->generateInstances(
                    $event->recurrence_rule,
                    $event->start_datetime,
                    now()->addYears(1),
                    100,
                    $event->cancelled_occurrences ?? []
                );

                // Find the next occurrence after now
                foreach ($instances as $instance) {
                    if ($instance['start']->isFuture()) {
                        $event->next_occurrence = $instance['start'];
                        $event->display_datetime = $instance['start'];
                        break;
                    }
                }

                // If no future occurrence found, use original date
                if (!isset($event->next_occurrence)) {
                    $event->next_occurrence = $event->start_datetime;
                    $event->display_datetime = $event->start_datetime;
                }
            } else {
                $event->next_occurrence = $event->start_datetime;
                $event->display_datetime = $event->start_datetime;
            }

            return $event;
        });

        return Inertia::render('Events/Index', [
            'events' => $events,
            'filters' => $request->only(['calendar_id', 'upcoming']),
        ]);
    }

    /**
     * Show the form for creating a new event
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
     */
    public function store(Request $request)
    {
        $this->authorize('create', Event::class);

        $validated = $request->validate([
            'calendar_id' => 'required|exists:calendars,id',
            'title' => 'required|string|max:255',
            'short_description' => 'required|string|max:1000',
            'long_description' => 'required|string',
            'featured_airports' => 'nullable|array',
            'featured_airports.*' => 'string|max:4',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'banner' => 'nullable|image|mimes:jpeg,jpg,png|max:5120',
            'recurrence_rule' => 'nullable|string',
            'discord_staffing_channel_id' => 'nullable|string|max:255',
        ]);

        // Validate that Discord channel is not already in use
        if (!empty($validated['discord_staffing_channel_id'])) {
            $existingEvent = Event::where('discord_staffing_channel_id', $validated['discord_staffing_channel_id'])
                ->first();

            if ($existingEvent) {
                return back()->withErrors([
                    'discord_staffing_channel_id' => 'This Discord channel is already in use by another event: ' . $existingEvent->title
                ])->withInput();
            }
        }

        // Validate recurrence rule if provided
        if (!empty($validated['recurrence_rule'])) {
            if (!$this->recurringEventService->validateRRule($validated['recurrence_rule'])) {
                return back()->withErrors(['recurrence_rule' => 'Invalid recurrence rule']);
            }
        }

        $bannerPath = null;
        if ($request->hasFile('banner')) {
            try {
                $bannerPath = $this->bannerUploadService->upload($request->file('banner'));
            } catch (\InvalidArgumentException $e) {
                return back()->withErrors(['banner' => $e->getMessage()]);
            }
        }

        $event = Event::create([
            ...$validated,
            'banner_path' => $bannerPath,
            'created_by' => $request->user()->id,
        ]);

        \Log::info('Event "' . $event->title . '" (' . $event->id . ') created by user: ' . $request->user()->vatsim_cid);

        return redirect()->route('events.show', $event)
            ->with('success', 'Event created successfully.');
    }

    /**
     * Display the specified event
     */
    public function show(Event $event)
    {
        $this->authorize('view', $event);

        $event->load(['calendar', 'creator', 'staffings.positions.bookedBy']);

        // If recurring, get upcoming instances
        $instances = [];
        if ($event->isRecurring()) {
            // Calculate event duration
            $duration = $event->start_datetime->diffInMinutes($event->end_datetime);

            $rawInstances = $this->recurringEventService->generateInstances(
                $event->recurrence_rule,
                $event->start_datetime,
                $event->start_datetime->copy()->addMonths(6),
                50,
                $event->cancelled_occurrences ?? []
            );

            // Filter for future instances only and apply the event duration
            $nextOccurrenceStart = null;
            $nextOccurrenceEnd = null;
            foreach ($rawInstances as $instance) {
                // Only include future occurrences
                if ($instance['start']->isFuture()) {
                    $instanceEnd = $instance['start']->copy()->addMinutes($duration);
                    $instances[] = [
                        'start' => $instance['start'],
                        'end' => $instanceEnd,
                        'cancelled' => $instance['cancelled'] ?? false,
                    ];

                    // The first future occurrence
                    if (!$nextOccurrenceStart) {
                        $nextOccurrenceStart = $instance['start'];
                        $nextOccurrenceEnd = $instanceEnd;
                    }
                }
            }

            // Set display_datetime to next occurrence as ISO 8601 string
            if ($nextOccurrenceStart) {
                $event->display_datetime = $nextOccurrenceStart->toISOString();
                $event->display_end_datetime = $nextOccurrenceEnd->toISOString();
            }
        }

        return Inertia::render('Events/Show', [
            'event' => $event,
            'instances' => $instances,
            'bannerUrl' => $event->banner_path
                ? $this->bannerUploadService->getUrl($event->banner_path)
                : null,
        ]);
    }

    /**
     * Show the form for editing the event
     */
    public function edit(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $calendars = Calendar::visibleTo($request->user())->get();

        return Inertia::render('Events/Edit', [
            'event' => $event,
            'calendars' => $calendars,
            'bannerUrl' => $event->banner_path
                ? $this->bannerUploadService->getUrl($event->banner_path)
                : null,
        ]);
    }

    /**
     * Update the specified event
     */
    public function update(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'calendar_id' => 'required|exists:calendars,id',
            'title' => 'required|string|max:255',
            'short_description' => 'required|string|max:1000',
            'long_description' => 'required|string',
            'staffing_description' => 'nullable|string',
            'featured_airports' => 'nullable|array',
            'featured_airports.*' => 'string|max:4',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'banner' => 'nullable|image|mimes:jpeg,jpg,png|max:5120',
            'recurrence_rule' => 'nullable|string',
            'discord_staffing_channel_id' => 'nullable|string|max:255',
            'remove_banner' => 'nullable|boolean',
        ]);

        // Validate that Discord channel is not already in use by another event
        if (!empty($validated['discord_staffing_channel_id'])) {
            $existingEvent = Event::where('discord_staffing_channel_id', $validated['discord_staffing_channel_id'])
                ->where('id', '!=', $event->id)
                ->first();

            if ($existingEvent) {
                return back()->withErrors([
                    'discord_staffing_channel_id' => 'This Discord channel is already in use by another event: ' . $existingEvent->title
                ])->withInput();
            }
        }

        // Validate recurrence rule if provided
        if (!empty($validated['recurrence_rule'])) {
            if (!$this->recurringEventService->validateRRule($validated['recurrence_rule'])) {
                return back()->withErrors(['recurrence_rule' => 'Invalid recurrence rule']);
            }
        }

        // Handle banner removal
        if ($request->boolean('remove_banner') && $event->banner_path) {
            $this->bannerUploadService->delete($event->banner_path);
            $validated['banner_path'] = null;
        }

        // Handle new banner upload
        if ($request->hasFile('banner')) {
            // Delete old banner
            if ($event->banner_path) {
                $this->bannerUploadService->delete($event->banner_path);
            }

            try {
                $validated['banner_path'] = $this->bannerUploadService->upload($request->file('banner'));
            } catch (\InvalidArgumentException $e) {
                return back()->withErrors(['banner' => $e->getMessage()]);
            }
        }

        $event->update($validated);

        // If staffing description was changed and event has Discord message, update it
        if (isset($validated['staffing_description']) && $event->discord_staffing_message_id) {
            \App\Jobs\UpdateDiscordStaffingMessage::dispatch($event->id, 'updated');
        }

        \Log::info('Event "' . $event->title . '" (' . $event->id . ') updated by user: ' . auth()->user()->vatsim_cid);

        return redirect()->route('events.show', $event)
            ->with('success', 'Event updated successfully.');
    }

    /**
     * Remove the specified event
     */
    public function destroy(Event $event)
    {
        $this->authorize('delete', $event);

        // Delete banner if exists
        if ($event->banner_path) {
            $this->bannerUploadService->delete($event->banner_path);
        }

        \Log::info('Event "' . $event->title . '" (' . $event->id . ') deleted by user: ' . auth()->user()->vatsim_cid);

        $event->delete();

        return redirect()->route('events.index')
            ->with('success', 'Event deleted successfully.');
    }

    /**
     * Cancel a specific occurrence of a recurring event
     */
    public function cancelOccurrence(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        if (!$event->isRecurring()) {
            return back()->withErrors(['error' => 'Only recurring events can have occurrences cancelled.']);
        }

        $validated = $request->validate([
            'occurrence_date' => 'required|date',
        ]);

        $event->cancelOccurrence($validated['occurrence_date']);

        \Log::info('Occurrence cancelled for event "' . $event->title . '" (' . $event->id . ') on ' . $validated['occurrence_date'] . ' by user: ' . auth()->user()->vatsim_cid);

        return back()->with('success', 'Occurrence cancelled successfully.');
    }

    /**
     * Uncancel a specific occurrence of a recurring event
     */
    public function uncancelOccurrence(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        if (!$event->isRecurring()) {
            return back()->withErrors(['error' => 'Only recurring events can have occurrences uncancelled.']);
        }

        $validated = $request->validate([
            'occurrence_date' => 'required|date',
        ]);

        $event->uncancelOccurrence($validated['occurrence_date']);

        \Log::info('Occurrence uncancelled for event "' . $event->title . '" (' . $event->id . ') on ' . $validated['occurrence_date'] . ' by user: ' . auth()->user()->vatsim_cid);

        return back()->with('success', 'Occurrence uncancelled successfully.');
    }

    /**
     * Show page for managing occurrences of a recurring event
     */
    public function manageOccurrences(Event $event)
    {
        $this->authorize('update', $event);

        if (!$event->isRecurring()) {
            return redirect()->route('events.show', $event)
                ->withErrors(['error' => 'Only recurring events have occurrences to manage.']);
        }

        $event->load(['calendar']);

        // Calculate event duration
        $duration = $event->start_datetime->diffInMinutes($event->end_datetime);

        // Get all instances (including cancelled) for management
        $rawInstances = $this->recurringEventService->generateAllInstances(
            $event->recurrence_rule,
            $event->start_datetime,
            $event->start_datetime->copy()->addMonths(12),
            100,
            $event->cancelled_occurrences ?? []
        );

        // Apply event duration and format
        $occurrences = [];
        foreach ($rawInstances as $instance) {
            $instanceEnd = $instance['start']->copy()->addMinutes($duration);
            $occurrences[] = [
                'start' => $instance['start']->toISOString(),
                'end' => $instanceEnd->toISOString(),
                'cancelled' => $instance['cancelled'],
            ];
        }

        return Inertia::render('Events/ManageOccurrences', [
            'event' => $event,
            'occurrences' => $occurrences,
        ]);
    }

    /**
     * Upload banner for an event
     */
    public function uploadBanner(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $request->validate([
            'banner' => 'required|image|mimes:jpeg,jpg,png|max:5120',
        ]);

        // Delete old banner
        if ($event->banner_path) {
            $this->bannerUploadService->delete($event->banner_path);
        }

        try {
            $bannerPath = $this->bannerUploadService->upload($request->file('banner'));

            $event->update(['banner_path' => $bannerPath]);

            return back()->with('success', 'Banner uploaded successfully.');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['banner' => $e->getMessage()]);
        }
    }
}
