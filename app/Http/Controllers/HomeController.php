<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\EventOccurrence;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $canManage = $request->user()?->can('manage events');

        $upcomingEvents = Event::with(['calendar', 'occurrences', 'futureOccurrences'])
            ->withMin(
                [
                    'occurrences as next_occurrence_at' => fn($q) => $q
                        ->where('start_time', '>=', now())
                        ->where('status', '!=', 'cancelled'),
                ],
                'start_time'
            )
            ->whereHas(
                'occurrences',
                fn($q) => $q
                    ->where('start_time', '>=', now())
                    ->where('status', '!=', 'cancelled')
            )
            ->when(!$canManage, fn($q) => $q->where('status', 'published'))
            ->orderBy('next_occurrence_at')
            ->take(3)
            ->get();

        $calendarEvents = EventOccurrence::with('event.calendar')
            ->where('start_time', '>=', now()->startOfMonth())
            ->where('start_time', '<=', now()->addMonths(6))
            ->whereHas('event', fn($q) => $q->when(!$canManage, fn($q) => $q->where('status', 'published')))
            ->get()
            ->map(fn($occ) => [
                'id'        => $occ->event->slug,
                'title'     => $occ->event->title,
                'start'     => \Carbon\Carbon::parse($occ->start_time)->utc()->toIso8601String(),
                'end'       => \Carbon\Carbon::parse($occ->end_time)->utc()->toIso8601String(),
                'cancelled' => $occ->status === 'cancelled',
                'url'       => '/events/' . $occ->event->slug,
            ]);

        return Inertia::render('Home', [
            'upcomingEvents' => EventResource::collection($upcomingEvents),
            'calendarEvents' => $calendarEvents,
        ]);
    }
}
