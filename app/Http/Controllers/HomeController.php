<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\Event;
use Carbon\Carbon;
use App\Models\EventInstance;

class HomeController extends Controller
{
    /**
     * Show the landing page if not logged in, or redirect if logged in.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $calendar = Calendar::where('public', 1)->first();

        $upcomingEvents = Event::whereHas('calendar', fn($q) => $q->where('public', 1))
            ->whereHas('instances', fn($q) => $q->where('start_time', '>=', now()))
            ->with(['nextInstance']) 
            ->get()
            ->sortBy(fn($event) => $event->nextInstance->start_time)
            ->take(5);

        $events = collect();
        if ($calendar) {
            $events = EventInstance::whereHas('event', function ($query) use ($calendar) {
                    $query->where('calendar_id', $calendar->id);
                })
                ->with('event:id,title')
                ->get()
                ->map(fn($instance) => [
                    'id'    => $instance->event_id,
                    'title' => $instance->event->title,
                    'start' => $instance->start_time->toIso8601String(),
                    'end'   => $instance->end_time->toIso8601String(),
                    'url'   => route('events.show', [
                        'event' => $instance->event_id, 
                        'instance' => $instance->id
                    ]),
                ]);
        }

        return view('home', compact('upcomingEvents', 'events', 'calendar'));
    }
}
