<?php

namespace App\Http\Controllers;

use App\Actions\EventSchedule;
use App\Actions\RecordAudit;
use App\Actions\RosterMutation;
use App\Models\EventRoster;
use App\Models\RosterSlot;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RosterBookingController extends Controller
{
    public function store(Request $request, EventRoster $roster, RosterSlot $slot, RosterMutation $mutation, EventSchedule $schedule, RecordAudit $audit): RedirectResponse
    {
        Gate::authorize('participate', $roster);
        abort_unless($roster->slots()->where('roster_slots.id', $slot->id)->exists(), 404);
        DB::transaction(function () use ($request, $roster, $slot, $mutation, $schedule, $audit): void {
            User::whereKey($request->user()->cid)->toBase()->update(['cid' => DB::raw('cid')]);
            $roster = $mutation->lockRoster($roster);
            Gate::authorize('participate', $roster);
            $slot = $roster->slots()->where('roster_slots.id', $slot->id)->lockForUpdate()->firstOrFail();
            if ($roster->mode !== 'pre_slotted') {
                throw ValidationException::withMessages(['booking' => 'This roster does not accept slot bookings.']);
            }
            if ($slot->booked_by !== null && $slot->booked_by !== $request->user()->cid) {
                throw ValidationException::withMessages(['booking' => 'This slot has already been booked.']);
            }
            if (! $slot->starts_at->isFuture()) {
                throw ValidationException::withMessages(['booking' => 'This slot has already started.']);
            }
            $overlapping = RosterSlot::where('booked_by', $request->user()->cid)->whereKeyNot($slot->id)
                ->where('starts_at', '<', $slot->ends_at)->where('ends_at', '>', $slot->starts_at)
                ->with('shift.roster.event.cancellations')->get();
            foreach ($overlapping as $otherSlot) {
                $otherRoster = $otherSlot->shift->roster;
                $occurrence = $schedule->occurrence($otherRoster->event, $otherRoster->occurrence_date);
                if ($occurrence !== null && $occurrence['status'] === 'scheduled') {
                    throw ValidationException::withMessages(['booking' => 'You already have a booking that overlaps this slot.']);
                }
            }
            $before = $roster->auditValues();
            $slot->update(['booked_by' => $request->user()->cid]);
            $audit->handle($roster, 'booked', $before, $roster->auditValues());
        });

        return to_route('events.roster.show', ['event' => $roster->event_id, 'date' => $roster->occurrence_date]);
    }

    public function destroy(Request $request, EventRoster $roster, RosterSlot $slot, RosterMutation $mutation, RecordAudit $audit): RedirectResponse
    {
        Gate::authorize('view', $roster);
        abort_unless($roster->slots()->where('roster_slots.id', $slot->id)->exists(), 404);
        DB::transaction(function () use ($request, $roster, $slot, $mutation, $audit): void {
            User::whereKey($request->user()->cid)->toBase()->update(['cid' => DB::raw('cid')]);
            $roster = $mutation->lockRoster($roster);
            Gate::authorize('view', $roster);
            $slot = $roster->slots()->where('roster_slots.id', $slot->id)->lockForUpdate()->firstOrFail();
            abort_unless($slot->booked_by === $request->user()->cid, 403);
            $before = $roster->auditValues();
            $slot->update(['booked_by' => null]);
            $audit->handle($roster, 'withdrawn', $before, $roster->auditValues());
        });

        return to_route('events.roster.show', ['event' => $roster->event_id, 'date' => $roster->occurrence_date]);
    }
}
