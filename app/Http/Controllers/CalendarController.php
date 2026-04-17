<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCalendarRequest;
use App\Http\Requests\UpdateCalendarRequest;
use App\Http\Resources\CalendarResource;
use App\Models\Calendar;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class CalendarController extends Controller
{
    /**
     * Display a listing of the calendars.
     */
    public function index()
    {
        $this->authorize('viewAny', Calendar::class);

        return Inertia::render('Calendars/Index', [
            'calendars' => CalendarResource::collection(Calendar::with(['creator', 'events'])->get()),
        ]);
    }

    /**
     * Show the form for creating a new calendar.
     */
    public function create()
    {
        $this->authorize('create', Calendar::class);

        return Inertia::render('Calendars/Create');
    }

    /**
     * Store a newly created calendar in storage.
     */
    public function store(StoreCalendarRequest $request)
    {
        $validated = $request->validated();

        $calendar = Calendar::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'visibility' => $validated['visibility'],
            'created_by' => $request->user()->id,
        ]);

        Log::info('Calendar created', ['calendar_id' => $calendar->id, 'created_by' => $request->user()->id]);

        return redirect()->route('calendars.show', $calendar);
    }

    /**
     * Display the specified calendar.
     */
    public function show(Calendar $calendar)
    {
        $this->authorize('view', $calendar);

        $calendar->load(['creator', 'events']);

        return Inertia::render('Calendars/Show', [
            'calendar' => new CalendarResource($calendar),
        ]);
    }

    /**
     * Show the form for editing the specified calendar.
     */
    public function edit(Calendar $calendar)
    {
        $this->authorize('update', $calendar);

        return Inertia::render('Calendars/Edit', [
            'calendar' => new CalendarResource($calendar),
        ]);
    }

    /**
     * Update the specified calendar in storage.
     */
    public function update(UpdateCalendarRequest $request, Calendar $calendar)
    {
        $validated = $request->validated();

        $calendar->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'visibility' => $validated['visibility'],
        ]);

        Log::info('Calendar updated', ['calendar_id' => $calendar->id, 'updated_by' => $request->user()->id]);

        return redirect()->route('calendars.show', $calendar);
    }

    /**
     * Remove the specified calendar from storage.
     */
    public function destroy(Calendar $calendar)
    {
        $this->authorize('delete', $calendar);

        $calendar->delete();

        Log::info('Calendar deleted', ['calendar_id' => $calendar->id]);

        return redirect()->route('calendars.index');
    }
}
