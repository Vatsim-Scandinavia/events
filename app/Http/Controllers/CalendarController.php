<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('index', Calendar::class);

        $calendars = Calendar::all();

        return view('calendar.index', compact('calendars'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Calendar::class);

        return view('calendar.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Calendar::class);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'public' => 'nullable|boolean',
        ]);

        Calendar::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'public' => $request->input('public'),
        ]);

        return redirect()->route('calendars.index')->withSuccess('Calendar has been created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Calendar $calendar)
    {
        $this->authorize('view', $calendar);

        $allEvents = $calendar->events()->get();
        $events = $allEvents->map(function ($event) {
            return [
                'id' => $event->id,
                'title' => $event->title,
                'start' => $event->start_date,
                'end' => $event->end_date,
                'url' => route('events.show', $event->id),
            ];
        });

        return view('calendar.show', compact('calendar', 'events'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Calendar $calendar)
    {
        $this->authorize('update', $calendar);

        return view('calendar.edit', compact('calendar'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Calendar $calendar)
    {
        $this->authorize('update', $calendar);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'public' => 'nullable|boolean',
        ]);

        $calendar->update([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'public' => $request->input('public'),
        ]);

        return redirect()->route('calendars.index')->withSuccess('Calendar updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Calendar $calendar)
    {
        $this->authorize('destroy', $calendar);

        $calendar->delete();

        return redirect()->route('calendars.index')->withSuccess('Successfully deleted calendar');
    }
}
