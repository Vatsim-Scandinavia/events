<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function __construct(
        protected EventService $eventService
    ) {}

    public function index(Request $request)
    {
        $events = Event::with(['calendar'])
            ->whereHas('calendar', fn($q) => $q->where('is_public', true))
            ->get();

        $calendarEvents = collect();
        $upcomingEvents = collect();

        $startDate = now()->startOfMonth();
        $endDate = now()->addMonths(3)->endOfMonth();

        foreach ($events as $event) {
            $instances = $this->eventService->generateUpcomingInstances($event, limit: 50);

            foreach ($instances as $instance) {
                $instanceStart = \Illuminate\Support\Carbon::parse($instance['start']);
                $instanceEnd = \Illuminate\Support\Carbon::parse($instance['end']);

                if ($instance['cancelled']) {
                    continue;
                }

                if ($instanceStart->gte($startDate) && $instanceStart->lte($endDate)) {
                    $calendarEvents->push([
                        'id' => $event->id . '-' . $instanceStart->timestamp,
                        'title' => $event->title,
                        'start' => $instance['start'],
                        'end' => $instance['end'],
                        'calendar' => $event->calendar->name,
                        'url' => route('events.show', $event),
                    ]);
                }

                if ($instanceStart->isFuture()) {
                    $eventWithDate = clone $event;
                    $eventWithDate->display_datetime = $instance['start'];
                    $upcomingEvents->push($eventWithDate);
                }
            }
        }

        $sortedUpcoming = $upcomingEvents
            ->sortBy('display_datetime')
            ->unique('id')
            ->take(3)
            ->values();

        return Inertia::render('Home', [
            'upcomingEvents' => $sortedUpcoming,
            'calendarEvents' => $calendarEvents,
        ]);
    }
}