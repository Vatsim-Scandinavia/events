<?php

namespace App\Http\Controllers;

use App\Actions\EventSchedule;
use App\Http\Requests\RosterIndexRequest;
use App\Models\EventRoster;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class RosterDirectoryController extends Controller
{
    public function index(RosterIndexRequest $request, EventSchedule $schedule): Response
    {
        return Inertia::render('events/rosters', [
            'rosters' => EventRoster::visibleTo($request->user())
                ->with(['event.owner', 'event.cancellations'])
                ->orderByDesc('occurrence_date')->orderByDesc('id')
                ->paginate(12)->withQueryString()
                ->through(function (EventRoster $roster) use ($schedule): array {
                    $occurrence = $schedule->occurrence($roster->event, $roster->occurrence_date);

                    return [
                        'id' => $roster->id,
                        'event_id' => $roster->event_id,
                        'title' => $roster->event->title,
                        'owner_code' => $roster->event->owner->code,
                        'mode' => $roster->mode,
                        'is_open' => $roster->is_open,
                        'occurrence' => $occurrence,
                        'has_ended' => $occurrence['ends_at'] !== null && CarbonImmutable::parse($occurrence['ends_at'])->isPast(),
                    ];
                }),
        ]);
    }
}
