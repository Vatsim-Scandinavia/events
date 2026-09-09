<?php

namespace App\Http\Controllers;

use App\Actions\EventSchedule;
use App\Actions\RecordAudit;
use App\Actions\RosterMutation;
use App\Actions\RosterSchedule;
use App\Http\Requests\RosterOccurrenceRequest;
use App\Models\EventRoster;
use App\Models\RosterBooking;
use App\Models\RosterSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RosterBookingController extends Controller
{
    public function store(RosterOccurrenceRequest $request, EventRoster $roster, RosterSlot $slot, RosterMutation $mutation, EventSchedule $schedule, RosterSchedule $rosterSchedule, RecordAudit $audit): RedirectResponse
    {
        $date = $request->validated('occurrence_date');
        Gate::authorize('participate', [$roster, $date]);
        abort_unless($roster->slots()->where('roster_slots.id', $slot->id)->exists(), 404);
        DB::transaction(function () use ($request, $roster, $slot, $date, $mutation, $schedule, $rosterSchedule, $audit): void {
            User::whereKey($request->user()->cid)->toBase()->update(['cid' => DB::raw('cid')]);
            $roster = $mutation->lockRoster($roster);
            Gate::authorize('participate', [$roster, $date]);
            $slot = $roster->slots()->where('roster_slots.id', $slot->id)->with('shift')->lockForUpdate()->firstOrFail();
            if ($roster->mode !== 'pre_slotted') {
                throw ValidationException::withMessages(['booking' => 'This roster does not accept slot bookings.']);
            }
            $times = $rosterSchedule->timesFor($slot, $date, $roster->event);
            if ($times === null) {
                throw ValidationException::withMessages(['booking' => 'This slot is unavailable on the selected occurrence because its local time does not exist.']);
            }
            if (! $times['starts_at']->isFuture()) {
                throw ValidationException::withMessages(['booking' => 'This slot has already started.']);
            }
            $existingBooking = $roster->bookings()->where('slot_id', $slot->id)->where('occurrence_date', $date)->first();
            if ($existingBooking !== null) {
                if ($existingBooking->user_cid !== $request->user()->cid) {
                    throw ValidationException::withMessages(['booking' => 'This slot has already been booked.']);
                }

                return;
            }
            $overlapping = RosterBooking::where('starts_at', '<', $times['ends_at'])->where('ends_at', '>', $times['starts_at'])
                ->where(fn (Builder $query) => $query->where('user_cid', $request->user()->cid)
                    ->orWhere(fn (Builder $query) => $query->where('roster_id', $roster->id)->where('occurrence_date', $date)->where('callsign', $slot->callsign)))
                ->with('roster.event.cancellations')->get();
            foreach ($overlapping as $booking) {
                $occurrence = $schedule->occurrence($booking->roster->event, $booking->occurrence_date);
                if ($occurrence !== null && $occurrence['status'] === 'scheduled') {
                    throw ValidationException::withMessages(['booking' => $booking->user_cid === $request->user()->cid
                        ? 'You already have a booking that overlaps this slot.' : 'This position already has an overlapping booking.']);
                }
            }
            $before = $roster->auditValues();
            $roster->bookings()->create([
                'slot_id' => $slot->id, 'user_cid' => $request->user()->cid, 'occurrence_date' => $date,
                'callsign' => $slot->callsign, 'shift_name' => $slot->shift->name,
                'starts_at' => $times['starts_at'], 'ends_at' => $times['ends_at'],
            ]);
            $audit->handle($roster, 'booked', $before, $roster->auditValues());
        });

        return to_route('events.roster.show', ['event' => $roster->event_id, ...($request->boolean('return_to_current') ? [] : ['date' => $date])]);
    }

    public function destroy(RosterOccurrenceRequest $request, EventRoster $roster, RosterSlot $slot, RosterMutation $mutation, RecordAudit $audit): RedirectResponse
    {
        abort_unless($roster->slots()->where('roster_slots.id', $slot->id)->exists(), 404);
        $booking = $roster->bookings()->where('slot_id', $slot->id)->where('occurrence_date', $request->validated('occurrence_date'))->first();
        abort_if($booking === null, 403);

        return $this->withdraw($request, $roster, $booking, $mutation, $audit);
    }

    public function withdraw(RosterOccurrenceRequest $request, EventRoster $roster, RosterBooking $booking, RosterMutation $mutation, RecordAudit $audit): RedirectResponse
    {
        $date = $request->validated('occurrence_date');
        abort_unless($booking->roster_id === $roster->id && $booking->occurrence_date === $date, 404);
        DB::transaction(function () use ($request, $roster, $booking, $mutation, $audit): void {
            User::whereKey($request->user()->cid)->toBase()->update(['cid' => DB::raw('cid')]);
            $roster = $mutation->lockRoster($roster);
            Gate::authorize('view', $roster);
            $booking = $roster->bookings()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            abort_unless($booking->user_cid === $request->user()->cid, 403);
            $before = $roster->auditValues();
            $booking->delete();
            $audit->handle($roster, 'withdrawn', $before, $roster->auditValues());
        });

        return to_route('events.roster.show', ['event' => $roster->event_id, ...($request->boolean('return_to_current') ? [] : ['date' => $date])]);
    }
}
