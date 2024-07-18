<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Calendar;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;


class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('index', Event::class);

        $events = Event::orderBy('start_date', 'ASC')->get();
        
        return view('events.index', compact('events'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Event::class);

        $areas = Area::all();

        $calendars = Calendar::all();

        return view('events.create', compact('areas', 'calendars'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Event::class);

        $this->validate($request, [
            'calendar_id' => 'required|exists:calendars,id',
            'area' => 'required|exists:areas,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable',
            'start_date' => 'required|date_format:Y-m-d H:i|after_or_equal:today',
            'end_date' => 'required|date_format:Y-m-d H:i|after_or_equal:start_date',
            'event_type' => 'integer',
            'recurrence_interval' => 'nullable|integer',
            'recurrence_unit' => 'nullable|string|max:255',
            'recurrence_end_date' => 'nullable|date_format:Y-m-d H:i|after_or_equal:end_date',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $event = Event::create([
            'calendar_id' => $request->input('calendar_id'),
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'start_date' => Carbon::parse($request->input('start_date'))->format('Y-m-d H:i'),
            'end_date' => Carbon::parse($request->input('end_date'))->format('Y-m-d H:i'),
            'is_full_day' => $request->input('event_type') == '2' ? true : false,
            'recurrence_interval' => $request->input('event_type') == '0' ? null : $request->input('recurrence_interval'),
            'recurrence_unit' => $request->input('event_type') == '0' ? null : $request->input('recurrence_unit'),
            'recurrence_end_date' => $request->input('event_type') == '0' ? null : $request->input('recurrence_end_date'),
            'image' => isset($imageName) ? $imageName : null,
        ]);

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '-' . uniqid() . '.' . $image->getClientOriginalExtension();
            $image->storeAs('images', $imageName, 'public');

            $event->image = $imageName;
        }

        // Ensure area and user association
        $event->area()->associate($request->input('area'));
        $event->user()->associate(\Auth::user());
        $event->save();

        // Generate and save recurrences if the event is recurring
        if ($event->recurrence_interval && $event->recurrence_unit) {
            $recurrences = $event->generateRecurrences();
            $event->children()->saveMany($recurrences);
        }

        return redirect()->route('events.index')->withSuccess('Event created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event)
    {
        $this->authorize('view', $event);

        return view('events.show', compact('event'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Event $event)
    {
        $this->authorize('update', $event);

        $areas = Area::all();

        $calendars = Calendar::all();

        return view('events.edit', compact('areas', 'calendars', 'event'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $this->validate($request, [
            'calendar_id' => 'required|exists:calendars,id',
            'area' => 'required|exists:areas,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable',
            'start_date' => 'required|date_format:Y-m-d H:i|after_or_equal:today',
            'end_date' => 'required|date_format:Y-m-d H:i|after_or_equal:start_date',
            'event_type' => 'integer',
            'recurrence_interval' => 'nullable|integer',
            'recurrence_unit' => 'nullable|string|max:255',
            'recurrence_end_date' => 'nullable|date_format:Y-m-d H:i|after_or_equal:end_date',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '-' . uniqid() . '.' . $image->getClientOriginalExtension();

            if ($event->image) {
                    Storage::disk('public')->delete('images/' . $event->image);
                }
            $image->storeAs('images', $imageName, 'public');
            $event->image = $imageName;
        }

        $event->update([
            'calendar_id' => $request->input('calendar_id'),
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'start_date' => Carbon::parse($request->input('start_date'))->format('Y-m-d H:i'),
            'end_date' =>  Carbon::parse($request->input('end_date'))->format('Y-m-d H:i'),
            'is_full_day' => $request->input('event_type') == '2' ? true : false,
            'recurrence_interval' => $request->input('event_type') == '0' ? null : $request->input('recurrence_interval'),
            'recurrence_unit' => $request->input('event_type') == '0' ? null : $request->input('recurrence_unit'),
            'recurrence_end_date' => $request->input('event_type') == '0' ? null : $request->input('recurrence_end_date'),
            'image' => isset($imageName) ? $imageName : $event->image,
        ]);

        // Ensure area and user association
        $event->area()->associate($request->input('area'));
        $event->user()->associate(\Auth::user());

        // Save the event before handling recurrences
        $event->save();

        // Check if the event is a recurring event and delete old recurrences if they exist
        if ($event->recurrence_interval && $event->recurrence_unit) {
            $event->children()->delete();

            // Generate and save new recurrences
            $recurrences = $event->generateRecurrences();
            $event->children()->saveMany($recurrences);
        }

        return redirect()->route('events.index')->withSuccess('Event updated successfully.');

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Event $event)
    {
        $this->authorize('destroy', $event);

        $event->delete();

        $event->children()->delete();

        return redirect()->route('events.index')->withSuccess('Event deleted successfully');
    }
}
