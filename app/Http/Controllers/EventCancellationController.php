<?php

namespace App\Http\Controllers;

use App\Actions\EventSchedule;
use App\Actions\RecordAudit;
use App\Actions\RosterMutation;
use App\Http\Requests\EventCancellationRequest;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EventCancellationController extends Controller
{
    public function store(EventCancellationRequest $request, Event $event, EventSchedule $schedule, RecordAudit $audit, RosterMutation $mutation): RedirectResponse
    {
        DB::transaction(function () use ($request, $event, $schedule, $audit, $mutation): void {
            $event = $mutation->lockEvent($event->id);
            $date = $request->validated('occurrence_date');
            Gate::authorize($date === null ? 'manageOwner' : 'update', $event);
            if ($event->status === 'cancelled') {
                return;
            }

            $before = $event->auditValues();
            if ($date !== null) {
                $occurrence = $schedule->occurrence($event, $date);
                if ($occurrence === null || $occurrence['status'] === 'skipped') {
                    throw ValidationException::withMessages(['occurrence_date' => 'Choose an occurrence in this event schedule.']);
                }
                $event->cancellations()->firstOrCreate(['occurrence_date' => $date], ['reason' => $request->validated('reason')]);
            } else {
                $event->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => $request->validated('reason')]);
            }
            $audit->handle($event, 'updated', $before, $event->auditValues());
        });

        return back();
    }

    public function destroy(EventCancellationRequest $request, Event $event, EventSchedule $schedule, RecordAudit $audit, RosterMutation $mutation): RedirectResponse
    {
        DB::transaction(function () use ($request, $event, $schedule, $audit, $mutation): void {
            $event = $mutation->lockEvent($event->id);
            $date = $request->validated('occurrence_date');
            Gate::authorize($date === null ? 'manageOwner' : 'update', $event);

            $before = $event->auditValues();
            if ($date !== null) {
                if ($event->status === 'cancelled') {
                    throw ValidationException::withMessages(['occurrence_date' => 'Restore the event before restoring individual occurrences.']);
                }
                $occurrence = $schedule->occurrence($event, $date);
                if ($occurrence === null || $occurrence['status'] === 'skipped') {
                    throw ValidationException::withMessages(['occurrence_date' => 'Choose an occurrence in this event schedule.']);
                }
                $event->cancellations()->where('occurrence_date', $date)->delete();
            } elseif ($event->status === 'cancelled') {
                $event->update(['status' => 'draft', 'published_at' => null, 'cancelled_at' => null, 'cancellation_reason' => null]);
            }
            $audit->handle($event, 'updated', $before, $event->auditValues());
        });

        return back();
    }
}
