<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Calendar $calendar) 
    {
        $events = $calendar
            ->events()
            ->get();

        return response()->json(['data' => $events->values()], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) 
    {
        $data = $this->validate($request, [
            'calendar_id' => 'required|exists:calendars,id',
            'area' => 'required|exists:areas,id',
            'user' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable',
            'start_date' => 'required|date_format:Y-m-d H:i|after_or_equal:today',
            'end_date' => 'required|date_format:Y-m-d H:i|after_or_equal:start_date',
            'event_type' => 'required|integer',
            'recurrence_interval' => 'nullable|integer',
            'recurrence_unit' => 'nullable|string|max:255',
            'recurrence_end_date' => 'nullable|date_format:Y-m-d H:i|after_or_equal:end_date',
        ]);

        $user = User::findorFail($data['user']);

        $this->authorize('create', Event::class);

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
        ]);

        // Ensure area and user association
        $event->area()->associate($request->input('area'));
        $event->user()->associate($user);
        $event->save();

        // Generate and save recurrences if the event is recurring
        if ($event->recurrence_interval && $event->recurrence_unit) {
            $recurrences = $event->generateRecurrences();
            $event->children()->saveMany($recurrences);
        }

        return response()->json([
            'success' => 'Event created',
            'event' => $event,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event) 
    {
        return response()->json(['event' => $event,], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Event $event) 
    {
        $data = $this->validate($request, [
            'calendar_id' => 'required|exists:calendars,id',
            'area' => 'required|exists:areas,id',
            'user' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable',
            'start_date' => 'required|date_format:Y-m-d H:i|after_or_equal:today',
            'end_date' => 'required|date_format:Y-m-d H:i|after_or_equal:start_date',
            'event_type' => 'integer',
            'recurrence_interval' => 'nullable|integer',
            'recurrence_unit' => 'nullable|string|max:255',
            'recurrence_end_date' => 'nullable|date_format:Y-m-d H:i|after_or_equal:end_date',
        ]);

        $user = User::findorFail($data['user']);

        $this->authorize('create', Event::class, $user);

        $event->update([
            'calendar_id' => $request->input('calendar_id'),
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'start_date' => Carbon::parse($request->input('start_date'))->format('Y-m-d H:i'),
            'end_date' => Carbon::parse($request->input('end_date'))->format('Y-m-d H:i'),
            'is_full_day' => $request->input('event_type') == '2' ? true : false,
            'recurrence_interval' => $request->input('event_type') == '0' ? null : $request->input('recurrence_interval'),
            'recurrence_unit' => $request->input('event_type') == '0' ? null : $request->input('recurrence_unit'),
            'recurrence_end_date' => $request->input('event_type') == '0' ? null : $request->input('recurrence_end_date'),
        ]);

        $event->area()->associate($request->input('area'));
        $event->user()->associate($user);
        $event->save();

        // Check if the event is a recurring event and delete old recurrences if they exist
        if ($event->recurrence_interval && $event->recurrence_unit) {
            $event->children()->delete();

            // Generate and save new recurrences
            $recurrences = $event->generateRecurrences();
            $event->children()->saveMany($recurrences);
        }

        return response()->json([
            'success' => 'Event updated',
            'event' => $event,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Event $event) 
    {
        $event->delete();

        $event->children()->delete();

        return response()->json([
            'success' => 'Event deleted',
            'event' => $event,
        ], 200);
    }
}