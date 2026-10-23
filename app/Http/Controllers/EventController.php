<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventDetailResource;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Staffing\StaffingPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Events/Index', [
            'events' => EventResource::collection(Event::upcoming()->get())->resolve(),
            'canCreate' => Gate::allows('manage-events'),
        ]);
    }

    public function show(Request $request, Event $event, StaffingPresenter $staffing): Response
    {
        // Controllers only staff the next date for now, so the public page always shows that one.
        $occurrence = $event->currentOccurrence() ?? abort(404);

        return Inertia::render('Events/Show', [
            'event' => (new EventDetailResource($event))->resolve(),
            'staffing' => $staffing->forEventPage($event, $occurrence, $request->user()),
            'canEdit' => Gate::allows('update', $event),
        ]);
    }
}
