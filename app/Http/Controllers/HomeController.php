<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\Event;
use Carbon\Carbon;

class HomeController extends Controller
{
    /**
     * Show the landing page if not logged in, or redirect if logged in.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Get events with start date today and the connected Calendar is public
        $upcomingEvents = Event::where('start_date', '>=', Carbon::today())
            ->whereHas('calendar', function($query) {
                $query->where('public', 1);
            })
            ->orderBy('start_date', 'asc')
            ->limit(5)
            ->get();

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
        

        return view('home', compact('upcomingEvents', 'events', 'calendar'));
    }
}
