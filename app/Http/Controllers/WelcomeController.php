<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\Event;
use Carbon\Carbon;

class WelcomeController extends Controller
{
    /**
     * Show the landing page if not logged in, or redirect if logged in.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $now = Carbon::now();
        $UpcomingEvents = Event::where('start_date', '>=', $now)
            ->orderBy('start_date', 'asc')
            ->get()
            ->filter(function ($event) {
                return $event->calendar->public;
            })
            ->take(5);

        $calendar = Calendar::where('public', 1)->first();

        if($calendar) {
            $allEvents = $calendar->events()->get();
            $events = $allEvents->map(function ($event){
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'start' => $event->start_date,
                    'end' => $event->end_date,
                    'url' => route('events.show', $event->id),
                ];
            });
        } else {
            $events = collect();
        }
        

        return view('welcome', compact('UpcomingEvents', 'events', 'calendar'));
    }
}
