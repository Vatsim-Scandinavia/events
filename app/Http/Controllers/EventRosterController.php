<?php

namespace App\Http\Controllers;

use App\Actions\EventSchedule;
use App\Actions\RosterSchedule;
use App\Actions\SaveEventRoster;
use App\Http\Requests\EventRosterRequest;
use App\Models\Event;
use App\Models\EventRoster;
use App\Models\RosterBooking;
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
    public function show(Request $request, Event $event, EventSchedule $schedule, RosterSchedule $rosterSchedule, ?string $date = null): Response
    {
        abort_unless($event->roster_enabled, 404);
        $roster = EventRoster::where('event_id', $event->id)->first();
        abort_unless(Gate::allows('view', $roster ?? $event), 404);
        $autoSelectOccurrence = $date === null;
        if ($date === null) {
            $date = $rosterSchedule->currentOrNext($event)['date'] ?? $roster?->bookings()->max('occurrence_date')
                ?? $roster?->interests()->max('occurrence_date') ?? substr($event->local_start, 0, 10);
        }
        abort_if(Validator::make(['date' => $date], ['date' => ['required', 'date_format:Y-m-d']])->fails(), 404);
        $occurrence = $schedule->occurrence($event, $date);
        abort_if($occurrence === null, 404);
        $canManage = $request->user()->can('update', $event);
        $canParticipate = $roster !== null && $request->user()->can('participate', [$roster, $date]);
        $roster?->load(['shifts.slots', 'positions', 'bookings' => fn ($query) => $query->where('occurrence_date', $date)->with('user'),
            'interests' => fn ($query) => $query->where('occurrence_date', $date)
                ->when(! $canManage, fn ($query) => $query->where('user_cid', $request->user()->cid))->with('user')]);
        $lockedSlotIds = $roster?->bookings()->where('ends_at', '>', now())->whereNotNull('slot_id')->pluck('slot_id')->all() ?? [];
        $lockedPositionIds = $roster?->interests()->where('occurrence_ends_at', '>', now())->get()->flatMap->position_ids->unique()->all() ?? [];
        $modeLocked = $roster !== null && ($roster->bookings()->where('ends_at', '>', now())->exists()
            || $roster->interests()->where('occurrence_ends_at', '>', now())->exists());
        $upcoming = $schedule->upcoming($event, $date, 12);
        $next = $rosterSchedule->nextAfter($event, $date);
        $historyDates = $roster === null ? [] : [
            ...$roster->bookings()->select('occurrence_date')->distinct()->orderByDesc('occurrence_date')->limit(24)->pluck('occurrence_date')->all(),
            ...$roster->interests()->select('occurrence_date')->distinct()->orderByDesc('occurrence_date')->limit(24)->pluck('occurrence_date')->all(),
        ];
        $options = collect([$date, ...($next === null ? [] : [$next['date']]), ...array_column($upcoming, 'date'), ...$historyDates])->unique()->sort()->values()
            ->map(fn (string $day): ?array => $schedule->occurrence($event, $day))->filter()->values();

        return Inertia::render('events/roster', [
            'event' => ['id' => $event->id, 'title' => $event->title, 'timezone' => $event->timezone],
            'occurrence' => [...$occurrence, 'has_ended' => $occurrence['ends_at'] !== null && CarbonImmutable::parse($occurrence['ends_at'])->isPast()],
            'autoSelectOccurrence' => $autoSelectOccurrence,
            'nextOccurrenceDate' => $next['date'] ?? null,
            'occurrenceOptions' => $options,
            'roster' => $roster === null ? null : [
                'id' => $roster->id, 'mode' => $roster->mode, 'is_open' => $roster->is_open, 'mode_locked' => $modeLocked,
                'shifts' => $roster->shifts->map(fn (RosterShift $shift): array => [
                    'id' => $shift->id, 'name' => $shift->name,
                    'slots' => $shift->slots->map(function (RosterSlot $slot) use ($rosterSchedule, $date, $event, $roster, $canParticipate, $lockedSlotIds): array {
                        $times = $rosterSchedule->timesFor($slot, $date, $event);
                        $booking = $roster->bookings->first(fn (RosterBooking $booking): bool => $booking->slot_id === $slot->id
                            && $booking->callsign === $slot->callsign && $times !== null
                            && $booking->starts_at->equalTo($times['starts_at']) && $booking->ends_at->equalTo($times['ends_at']));

                        return [
                            'id' => $slot->id, 'callsign' => $slot->callsign,
                            'is_locked' => in_array($slot->id, $lockedSlotIds), 'is_unavailable' => $times === null,
                            'can_book' => $canParticipate && $booking === null && $times !== null && $times['starts_at']->isFuture(),
                            'starts_at' => $times === null ? null : $times['starts_at']->format('Y-m-d\TH:i'),
                            'ends_at' => $times === null ? null : $times['ends_at']->format('Y-m-d\TH:i'),
                            'booking' => $booking === null ? null : ['id' => $booking->id, 'cid' => $booking->user->cid, 'name' => $booking->user->name_full],
                        ];
                    })->all(),
                ])->all(),
                'positions' => $roster->positions->map(fn (RosterPosition $position): array => [
                    ...$position->only(['id', 'callsign']), 'is_locked' => in_array($position->id, $lockedPositionIds),
                ])->all(),
                'bookings' => $roster->bookings->map(fn (RosterBooking $booking): array => [
                    'id' => $booking->id, 'slot_id' => $booking->slot_id, 'callsign' => $booking->callsign, 'shift_name' => $booking->shift_name,
                    'starts_at' => $booking->starts_at->format('Y-m-d\TH:i'), 'ends_at' => $booking->ends_at->format('Y-m-d\TH:i'),
                    'user' => ['cid' => $booking->user->cid, 'name' => $booking->user->name_full],
                    'can_withdraw' => $booking->user_cid === $request->user()->cid,
                ])->all(),
                'interests' => $roster->interests->map(fn (RosterInterest $interest): array => [
                    'id' => $interest->id, 'user' => ['cid' => $interest->user->cid, 'name' => $interest->user->name_full],
                    'position_ids' => $interest->position_ids, 'position_callsigns' => $interest->position_callsigns, 'availability' => $interest->availability,
                ])->all(),
            ],
            'canManage' => $canManage,
            'canParticipate' => $canParticipate,
            'canViewEvent' => $request->user()->can('view', $event),
            'currentUserCid' => $request->user()->cid,
        ]);
    }

    public function update(EventRosterRequest $request, Event $event, SaveEventRoster $save): RedirectResponse
    {
        $save->handle($request, $event);

        return to_route('events.roster.show', ['event' => $event, ...($request->boolean('return_to_current') ? [] : ['date' => $request->validated('occurrence_date')])]);
    }
}
