<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Calendar;
use App\Models\Event;
use App\Services\EventService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EventController extends Controller
{
    public function index(Calendar $calendar)
    {
        $events = $calendar->events()
            ->with(['instances' => function($query) {
                $query->where('start_time', '>=', now())->orderBy('start_time', 'asc');
            }])
            ->get();

        $events->transform(function ($event) {
            $event->image_url = $event->image ? asset('storage/banners/'.$event->image) : asset('images/tba.jpg');
            $event->web_link = route('events.show', $event->id);
            return $event;
        });

        return response()->json(['data' => $events], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'calendar_id' => 'required|exists:calendars,id',
            'title' => 'required|string|max:255',
            'short_description' => 'required|max:280',
            'long_description' => 'required',
            'start_date' => 'required|date_format:Y-m-d H:i',
            'end_date' => 'required|date_format:Y-m-d H:i|after_or_equal:start_date',
            'user' => 'required|exists:users,id',
            'image' => 'nullable|image|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $imageName = null;
        if ($request->hasFile('image')) {
            $imageName = now()->format('Y-m-d').'-'.uniqid().'.'.$request->file('image')->getClientOriginalExtension();
            $request->file('image')->storeAs('banners', $imageName, 'public');
        }

        DB::beginTransaction();
        try {
            $event = Event::create([
                'calendar_id' => $request->calendar_id,
                'user_id' => $request->user,
                'title' => $request->title,
                'short_description' => $request->short_description,
                'long_description' => $request->long_description,
                'image' => $imageName,
            ]);

            $event->instances()->create([
                'start_time' => Carbon::parse($request->start_date),
                'end_time' => Carbon::parse($request->end_date),
            ]);

            DB::commit();
            
            return response()->json(['success' => 'Event created', 'data' => $event->load('instances')], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($imageName) {
                Storage::disk('public')->delete('banners/'.$imageName);
            }

            Log::error('Event creation failed', ['exception' => $e]);
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }

    public function update(Request $request, Event $event)
    {
        $validator = Validator::make($request->all(), [
            'calendar_id' => 'required|exists:calendars,id',
            'title' => 'required|string|max:255',
            'start_date' => 'required|date_format:Y-m-d H:i',
            'end_date' => 'required|date_format:Y-m-d H:i|after_or_equal:start_date',
            'short_description' => 'nullable|string|max:255',
            'long_description' => 'nullable|string',
            'recurrence_interval' => 'required_with:recurrence_unit|nullable|integer|min:1',
            'recurrence_unit' => 'nullable|in:day,week,month,year',
            'recurrence_end_date' => 'required_with:recurrence_unit|nullable|date_format:Y-m-d H:i|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $data = [
                'calendar_id' => $request->calendar_id,
                'title' => $request->title,
                'short_description' => $request->short_description,
                'long_description' => $request->long_description,
                'start_date' => Carbon::parse($request->start_date),
                'end_date' => Carbon::parse($request->end_date),
                'recurrence_interval' => $request->recurrence_interval,
                'recurrence_unit' => $request->recurrence_unit,
                'recurrence_end_date' => $request->recurrence_end_date ? Carbon::parse($request->recurrence_end_date) : null,
            ];

            $eventService = app(EventService::class);
            $updatedEvent = $eventService->updateEvent($event, $data);

            return response()->json(['success' => 'Event updated', 'data' => $updatedEvent->load('instances')], 200);
        } catch (\Exception $e) {
            Log::error('Event update failed', ['exception' => $e]);
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }

    public function destroy(Event $event)
    {
        if ($event->image) {
            Storage::disk('public')->delete('banners/'.$event->image);
        }
        $event->instances()->delete();
        $event->delete();

        return response()->json(['success' => 'Event deleted'], 200);
    }
}
