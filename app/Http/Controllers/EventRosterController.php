<?php

namespace App\Http\Controllers;

use App\Actions\EventSchedule;
use App\Actions\SaveEventRoster;
use App\Http\Requests\EventRosterRequest;
use App\Models\Event;
use App\Models\EventRoster;
use App\Models\RosterInterest;
use App\Models\RosterPosition;
use App\Models\RosterShift;
use App\Models\RosterSlot;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class EventRosterController extends Controller
{
    public function show(Request $request, Event $event, string $date, EventSchedule $schedule): Response
    {
        $roster = EventRoster::where('event_id', $event->id)->where('occurrence_date', $date)->first();
        abort_unless(Gate::allows('view', $roster ?? $event), 404);
        abort_if(Validator::make(['date' => $date], ['date' => ['required', 'date_format:Y-m-d']])->fails(), 404);
        $occurrence = $schedule->occurrence($event, $date);
        abort_if($occurrence === null, 404);
        $canManage = $request->user()->can('update', $event);
        $canParticipate = $roster !== null && $request->user()->can('participate', $roster);
        $roster?->load(['shifts.slots.controller', 'positions', 'interests' => fn ($query) => $query
            ->when(! $canManage, fn ($query) => $query->where('user_cid', $request->user()->cid))->with('user')]);

        return Inertia::render('events/roster', [
            'event' => ['id' => $event->id, 'title' => $event->title, 'timezone' => $event->timezone],
            'occurrence' => [...$occurrence, 'has_ended' => $occurrence['ends_at'] !== null && CarbonImmutable::parse($occurrence['ends_at'])->isPast()],
            'roster' => $roster === null ? null : [
                'id' => $roster->id, 'mode' => $roster->mode, 'is_open' => $roster->is_open,
                'shifts' => $roster->shifts->map(fn (RosterShift $shift): array => [
                    'id' => $shift->id, 'name' => $shift->name,
                    'slots' => $shift->slots->map(fn (RosterSlot $slot): array => [
                        'id' => $slot->id, 'callsign' => $slot->callsign,
                        'can_book' => $canParticipate && $slot->booked_by === null && $slot->starts_at->isFuture(),
                        'starts_at' => $slot->starts_at->format('Y-m-d\TH:i'),
                        'ends_at' => $slot->ends_at->format('Y-m-d\TH:i'),
                        'booking' => $slot->controller === null ? null : ['cid' => $slot->controller->cid, 'name' => $slot->controller->name_full],
                    ])->all(),
                ])->all(),
                'positions' => $roster->positions->map(fn (RosterPosition $position): array => $position->only(['id', 'callsign']))->all(),
                'interests' => $roster->interests->map(fn (RosterInterest $interest): array => [
                    'id' => $interest->id, 'user' => ['cid' => $interest->user->cid, 'name' => $interest->user->name_full],
                    'position_ids' => $interest->position_ids, 'availability' => $interest->availability,
                ])->all(),
            ],
            'canManage' => $canManage,
            'canParticipate' => $canParticipate,
            'canViewEvent' => $request->user()->can('view', $event),
            'currentUserCid' => $request->user()->cid,
        ]);
    }

    public function update(EventRosterRequest $request, Event $event, string $date, SaveEventRoster $save): RedirectResponse
    {
        $save->handle($request, $event, $date);

        return to_route('events.roster.show', ['event' => $event, 'date' => $date]);
    }
}
