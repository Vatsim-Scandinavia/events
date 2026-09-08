<?php

namespace App\Http\Controllers;

use App\Actions\EventSchedule;
use App\Actions\RecordAudit;
use App\Http\Requests\EventCancellationRequest;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EventCancellationController extends Controller
{
    public function store(EventCancellationRequest $request, Event $event, EventSchedule $schedule, RecordAudit $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $event, $schedule, $audit): void {
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
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
}
