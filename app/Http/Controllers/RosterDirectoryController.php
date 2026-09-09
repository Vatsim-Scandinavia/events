<?php

namespace App\Http\Controllers;

use App\Actions\RosterSchedule;
use App\Http\Requests\RosterIndexRequest;
use App\Models\EventRoster;
use Inertia\Inertia;
use Inertia\Response;

class RosterDirectoryController extends Controller
{
    public function index(RosterIndexRequest $request, RosterSchedule $schedule): Response
    {
        return Inertia::render('events/rosters', [
            'rosters' => EventRoster::visibleTo($request->user())
                ->with(['event.owner', 'event.cancellations'])
                ->orderByDesc('id')
                ->paginate(12)->withQueryString()
                ->through(function (EventRoster $roster) use ($schedule): array {
                    $occurrence = $schedule->currentOrNext($roster->event);

                    return [
                        'id' => $roster->id,
                        'event_id' => $roster->event_id,
                        'title' => $roster->event->title,
                        'owner_code' => $roster->event->owner->code,
                        'mode' => $roster->mode,
                        'is_open' => $roster->is_open,
                        'occurrence' => $occurrence,
                        'has_ended' => $occurrence === null,
                    ];
                }),
        ]);
    }
}
