<?php

namespace App\Http\Controllers;

use App\Helpers\EventHelper;
use App\Models\Calendar;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

        $calendars = Calendar::all();

        return view('events.create', compact('calendars'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Event::class);

        $this->validate($request, [
            'calendar_id' => 'required|exists:calendars,id',
            'title' => 'required|string|max:255',
            'short_description' => 'required|max:280',
            'long_description' => 'required',
            'start_date' => 'required|date_format:Y-m-d H:i|after_or_equal:today',
            'end_date' => 'required|date_format:Y-m-d H:i|after_or_equal:start_date',
            'event_type' => 'integer',
            'recurrence_interval' => 'nullable|required_if:event_type,1|integer',
            'recurrence_unit' => 'nullable|required_if:event_type,1|string|max:255',
            'recurrence_end_date' => 'nullable|required_if:event_type,1|date_format:Y-m-d H:i|after_or_equal:end_date',
            'image' => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
        ]);

        $imageName = null;

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = now()->format('Y-m-d').'-'.uniqid().'.'.$image->getClientOriginalExtension();

            // Get image dimensions
            [$width, $height] = getimagesize($image->getPathName());
            if (round($width / $height, 2) != round(16 / 9, 2)) {
                return back()->withErrors(['image' => 'Image must be in 16:9 aspect ratio.'])->withInput();
            }

            // Store the image
            $storedPath = $image->storeAs('banners', $imageName, 'public');

            // Check if the image was successfully uploaded
            if (! $storedPath && ! Storage::disk('public')->exists($storedPath)) {
                return back()->withErrors(['image' => 'Failed to upload the image.'])->withInput();
            }

        }

        $event = Event::create([
            'calendar_id' => $request->input('calendar_id'),
            'title' => $request->input('title'),
            'short_description' => $request->input('short_description'),
            'long_description' => $request->input('long_description'),
            'start_date' => Carbon::parse($request->input('start_date'))->format('Y-m-d H:i'),
            'end_date' => Carbon::parse($request->input('end_date'))->format('Y-m-d H:i'),
            'recurrence_interval' => $request->input('event_type') == '0' ? null : $request->input('recurrence_interval'),
            'recurrence_unit' => $request->input('event_type') == '0' ? null : $request->input('recurrence_unit'),
            'recurrence_end_date' => $request->input('event_type') == '0' ? null : $request->input('recurrence_end_date'),
            'image' => $imageName,
            'published' => false,
        ]);

        // Ensure user association
        $event->user()->associate(\Auth::user());
        $event->save();

        if($event->calendar->public) {
            // Post to Discord
            EventHelper::discordPost(
                $event->id,
                ':calendar_spiral: A new event has been scheduled.',
                $event->title,
                $event->long_description,
                asset('storage/banners/'.$event->image),
                Carbon::parse($event->start_date),
                Carbon::parse($event->end_date)
            );
        }

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

        $calendars = Calendar::all();

        return view('events.edit', compact('calendars', 'event'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $this->validate($request, [
            'calendar_id' => 'required|exists:calendars,id',
            'title' => 'required|string|max:255',
            'short_description' => 'required|max:280',
            'long_description' => 'required',
            'start_date' => 'required|date_format:Y-m-d H:i',
            'end_date' => 'required|date_format:Y-m-d H:i|after_or_equal:start_date',
            'event_type' => 'integer',
            'recurrence_interval' => 'nullable|required_if:event_type,1|integer',
            'recurrence_unit' => 'nullable|required_if:event_type,1|string|max:255',
            'recurrence_end_date' => 'nullable|required_if:event_type,1|date_format:Y-m-d H:i|after_or_equal:end_date',
            'image' => 'nullable|image|mimes:jpeg,jpg,png|max:2048',
        ]);

        $imageName = $event->image;

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = now()->format('Y-m-d').'-'.uniqid().'.'.$image->getClientOriginalExtension();

            // Get image dimensions
            [$width, $height] = getimagesize($image->getPathName());
            if (round($width / $height, 2) != round(16 / 9, 2)) {
                return back()->withErrors(['image' => 'Image must be in 16:9 aspect ratio.']);
            }

            // Delete the old image if it exists
            if ($event->image) {
                Storage::disk('public')->delete('banners/'.$event->image);
            }

            // Store the new image
            $image->storeAs('banners', $imageName, 'public');

            // Check if the image was successfully uploaded
            if (! Storage::disk('public')->exists('banners/'.$imageName)) {
                return back()->withErrors(['image' => 'Failed to upload the image.']);
            }
        }

        $event->update([
            'calendar_id' => $request->input('calendar_id'),
            'title' => $request->input('title'),
            'short_description' => $request->input('short_description'),
            'long_description' => $request->input('long_description'),
            'start_date' => Carbon::parse($request->input('start_date'))->format('Y-m-d H:i'),
            'end_date' => Carbon::parse($request->input('end_date'))->format('Y-m-d H:i'),
            'recurrence_interval' => $request->input('event_type') == '0' ? null : $request->input('recurrence_interval'),
            'recurrence_unit' => $request->input('event_type') == '0' ? null : $request->input('recurrence_unit'),
            'recurrence_end_date' => $request->input('event_type') == '0' ? null : $request->input('recurrence_end_date'),
            'image' => $imageName,
        ]);

        // Ensure user association
        $event->user()->associate(\Auth::user());

        // Save the event before handling recurrences
        $event->save();

        if($event->calendar->public && $event->discordMessage) {
            // Post to Discord
            EventHelper::discordUpdate(
                $event->id,
                ':calendar_spiral: A new event has been scheduled.',
                $event->title,
                $event->long_description,
                asset('storage/banners/'.$event->image),
                Carbon::parse($event->start_date),
                Carbon::parse($event->end_date)
            );
        }

        // Check if the event is a recurring event and handle recurrences intelligently
        if ($event->recurrence_interval && $event->recurrence_unit) {
            $this->updateEventRecurrences($event);
        } else {
            // If the event is no longer recurring, delete all children
            $event->children()->delete();
        }

        return redirect()->route('events.index')->withSuccess('Event updated successfully.');
    }

    /**
     * Intelligently update recurring event children without recreating them
     */
    private function updateEventRecurrences(Event $event)
    {
        $existingChildren = $event->children()->orderBy('start_date', 'ASC')->get();
        $newRecurrences = $event->generateRecurrences();
        
        // Remove the parent event from generated recurrences (first element is always the parent)
        $newRecurrenceData = collect($newRecurrences)->slice(1)->values(); // Add values() to reindex
        
        $existingCount = $existingChildren->count();
        $newCount = $newRecurrenceData->count();
        
        // Update existing children with new data
        foreach ($existingChildren as $index => $existingChild) {
            if ($index < $newCount) {
                $newData = $newRecurrenceData[$index];
                $existingChild->update([
                    'title' => $newData->title,
                    'short_description' => $newData->short_description,
                    'long_description' => $newData->long_description,
                    'start_date' => $newData->start_date,
                    'end_date' => $newData->end_date,
                    'calendar_id' => $newData->calendar_id,
                    'recurrence_interval' => $newData->recurrence_interval,
                    'recurrence_unit' => $newData->recurrence_unit,
                    'recurrence_end_date' => $newData->recurrence_end_date,
                    'image' => $newData->image,
                ]);
            } else {
                // Delete excess children
                $existingChild->delete();
            }
        }
        
        // Create new children if needed
        if ($newCount > $existingCount) {
            $newChildren = $newRecurrenceData->slice($existingCount)->map(function ($newData) use ($event) {
                return new Event([
                    'title' => $newData->title,
                    'short_description' => $newData->short_description,
                    'long_description' => $newData->long_description,
                    'start_date' => $newData->start_date,
                    'end_date' => $newData->end_date,
                    'calendar_id' => $newData->calendar_id,
                    'parent_id' => $event->id,
                    'recurrence_interval' => $newData->recurrence_interval,
                    'recurrence_unit' => $newData->recurrence_unit,
                    'recurrence_end_date' => $newData->recurrence_end_date,
                    'image' => $newData->image,
                    'user_id' => $event->user_id,
                ]);
            });
            
            $event->children()->saveMany($newChildren);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Event $event)
    {
        $this->authorize('destroy', $event);

        // Handle staffings associated with this event before deletion
        if ($event->staffing) {
            $staffing = $event->staffing;
            $nextEvent = null;

            // Find the next appropriate event in the series
            if ($event->parent_id) {
                // This is a child event, look for next sibling or parent
                $parent = $event->parent;
                if ($parent && $parent->start_date > now()) {
                    $nextEvent = $parent;
                } else {
                    $nextEvent = $parent->children()
                        ->where('start_date', '>', now())
                        ->where('id', '!=', $event->id)
                        ->orderBy('start_date')
                        ->first();
                }
            } else {
                // This is a parent event, look for next child
                $nextEvent = $event->children()
                    ->where('start_date', '>', now())
                    ->orderBy('start_date')
                    ->first();
            }

            if ($nextEvent) {
                // Move staffing to the next event
                $staffing->event()->associate($nextEvent);
                $staffing->save();
            } else {
                // No future events found, delete the staffing
                $staffing->delete();
            }
        }

        // Delete the old image if it exists
        if ($event->image && $event->parent_id === null) {
            Storage::disk('public')->delete('banners/'.$event->image);
        }

        if($event->calendar->public && $event->discordMessage) {
            // Delete the Discord message
            EventHelper::discordDelete($event->discordMessage->message_id);
            $event->discordMessage->delete();
        }

        // Delete the event and any of its children
        $event->children()->delete();

        $event->delete();

        return redirect()->route('events.index')->withSuccess('Event deleted successfully');
    }
}
