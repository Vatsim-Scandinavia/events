<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

        // Set the full path on the image attribute
        $events->transform(function ($event) {
            $event->image = isset($event->image) ? asset('storage/banners/' . $event->image) : null;
            return $event;
        });

        return response()->json(['data' => $events->values()], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) 
    {
        $data = $this->validate($request, [
            'calendar_id' => 'required|exists:calendars,id',
            'title' => 'required|string|max:255',
            'short_description' => 'required|max:280',
            'long_description' => 'required',
            'start_date' => 'required|date_format:Y-m-d H:i|after_or_equal:today',
            'end_date' => 'required|date_format:Y-m-d H:i|after_or_equal:start_date',
            'event_type' => 'integer',
            'recurrence_interval' => 'nullable|integer',
            'recurrence_unit' => 'nullable|string|max:255',
            'recurrence_end_date' => 'nullable|date_format:Y-m-d H:i|after_or_equal:end_date',
            'image' => 'nullable|image|mimes:jpeg,jpg|max:2048',
        ]);

        $user = User::findorFail($data['user']);

        $this->authorize('create', Event::class);

        $imageName = null;

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = now()->format('Y-m-d') . '-' . uniqid() . '.' . $image->getClientOriginalExtension();
    
            // Get image dimensions
            list($width, $height) = getimagesize($image->getPathName());
            if (round($width / $height, 2) != round(16 / 9, 2)) {
                return back()->withErrors(['image' => 'Image must be in 16:9 aspect ratio.'])->withInput();
            }
    
            // Store the image
            $storedPath = $image->storeAs('banners', $imageName, 'public');
    
            // Check if the image was successfully uploaded
            if ($storedPath && !Storage::disk('public')->exists($storedPath)) {
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
        ]);

        // Ensure user association
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
            'title' => 'required|string|max:255',
            'short_description' => 'required|max:280',
            'long_description' => 'required',
            'start_date' => 'required|date_format:Y-m-d H:i|after_or_equal:' . Carbon::parse($event->start_date)->format('Y-m-d H:i'),
            'end_date' => 'required|date_format:Y-m-d H:i|after_or_equal:start_date',
            'event_type' => 'integer',
            'recurrence_interval' => 'nullable|integer',
            'recurrence_unit' => 'nullable|string|max:255',
            'recurrence_end_date' => 'nullable|date_format:Y-m-d H:i|after_or_equal:end_date',
            'image' => 'nullable|image|mimes:jpeg,jpg|max:2048',
        ]);

        $user = User::findorFail($data['user']);

        $this->authorize('create', Event::class, $user);

        $imageURL = $event->image;

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '-' . uniqid() . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->getPathName();
    
            // Get image dimensions
            list($width, $height) = getimagesize($imagePath);
            if ($width / $height != 16 / 9) {
                return back()->withErrors(['image' => 'Image must be in 16:9 aspect ratio.']);
            }
    
            // Delete the old image if it exists
            if ($event->image) {
                Storage::disk('public')->delete('images/' . $event->image);
            }
    
            // Store the new image
            $image->storeAs('images', $imageName, 'public');
    
            // Check if the image was successfully uploaded
            if (!Storage::disk('public')->exists('images/' . $imageName)) {
                return back()->withErrors(['image' => 'Failed to upload the image.']);
            }

            $imageURL = asset('storage/images/' . $imageName);
    
            $event->image = $imageURL;
        }

        $event->update([
            'calendar_id' => $request->input('calendar_id'),
            'title' => $request->input('title'),
            'short_description' => $request->input('short_description'),
            'long_description' => $request->input('long_description'),
            'start_date' => Carbon::parse($request->input('start_date'))->format('Y-m-d H:i'),
            'end_date' =>  Carbon::parse($request->input('end_date'))->format('Y-m-d H:i'),
            'recurrence_interval' => $request->input('event_type') == '0' ? null : $request->input('recurrence_interval'),
            'recurrence_unit' => $request->input('event_type') == '0' ? null : $request->input('recurrence_unit'),
            'recurrence_end_date' => $request->input('event_type') == '0' ? null : $request->input('recurrence_end_date'),
            'image' => $imageURL,
        ]);

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
