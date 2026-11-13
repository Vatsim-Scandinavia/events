<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventDetailResource;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\Fir;
use App\Services\LinkPreview;
use App\Staffing\StaffingPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    /**
     * Upcoming events, optionally narrowed to one FIR and one airport, both named by ICAO code
     * so the link can be shared. An unknown FIR is ignored. The airports to choose from are those
     * in the events shown for the chosen FIR, so the two filters can't rule each other out: an
     * airport outside the chosen FIR's events is dropped.
     */
    public function index(Request $request): Response
    {
        $firs = Fir::query()->orderBy('icao')->get(['id', 'icao', 'name']);
        $fir = $firs->firstWhere('icao', strtoupper(trim((string) $request->query('fir', ''))));
        $airport = strtoupper(trim((string) $request->query('airport', '')));
        $airport = preg_match('/^[A-Z0-9]{4}$/', $airport) ? $airport : null;

        $events = Event::upcoming()
            ->visibleTo($request->user())
            ->when($fir, fn ($query) => $query->where('fir_id', $fir->id))
            ->get();

        $airports = $events->pluck('airports')->flatten()->unique()->sort()->values();

        if ($airport !== null && $fir !== null && ! $airports->contains($airport)) {
            $airport = null;
        }

        return Inertia::render('Events/Index', [
            'events' => EventResource::collection(
                $airport === null ? $events : $events->filter(fn (Event $event) => in_array($airport, $event->airports ?? [], true))->values()
            )->resolve(),
            'filters' => ['fir' => $fir?->icao, 'airport' => $airport],
            'firs' => $firs->map(fn (Fir $fir) => ['icao' => $fir->icao, 'name' => $fir->name])->values(),
            // An airport chosen with no events still shows as chosen.
            'airports' => $airport === null ? $airports : $airports->push($airport)->unique()->sort()->values(),
            'canCreate' => Gate::allows('manage-events'),
        ]);
    }

    public function show(Request $request, Event $event, StaffingPresenter $staffing, LinkPreview $preview): Response
    {
        // A Draft is not found for anyone who may not see it, rather than forbidden.
        abort_unless(Gate::allows('view', $event), 404);

        // Controllers only staff the next date for now, so the public page always shows that one.
        $occurrence = $event->currentOccurrence() ?? abort(404);

        return Inertia::render('Events/Show', [
            'event' => (new EventDetailResource($event))->resolve(),
            'staffing' => $staffing->forEventPage($event, $occurrence, $request->user()),
            'canEdit' => Gate::allows('update', $event),
        ])->withViewData(['preview' => $preview->event($event)]);
    }
}
