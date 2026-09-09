<?php

namespace App\Http\Controllers;

use App\Actions\RecordAudit;
use App\Actions\RosterMutation;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EventPublicationController extends Controller
{
    public function store(Event $event, RosterMutation $mutation, RecordAudit $audit): RedirectResponse
    {
        DB::transaction(function () use ($event, $mutation, $audit): void {
            $event = $mutation->lockEvent($event->id);
            Gate::authorize('publish', $event);
            if ($event->status === 'cancelled') {
                throw ValidationException::withMessages(['event' => 'Restore the event before publishing it.']);
            }
            if ($event->isPubliclyVisible()) {
                return;
            }

            $before = $event->auditValues();
            $event->update(['status' => 'published', 'published_at' => now()]);
            $audit->handle($event, 'published', $before, $event->auditValues());
        });

        return to_route('events.show', $event);
    }

    public function destroy(Event $event, RosterMutation $mutation, RecordAudit $audit): RedirectResponse
    {
        DB::transaction(function () use ($event, $mutation, $audit): void {
            $event = $mutation->lockEvent($event->id);
            Gate::authorize('publish', $event);
            if ($event->published_at === null && $event->status !== 'published') {
                return;
            }

            $before = $event->auditValues();
            $event->update([
                'status' => $event->status === 'published' ? 'draft' : $event->status,
                'published_at' => null,
            ]);
            $audit->handle($event, 'unpublished', $before, $event->auditValues());
        });

        return to_route('events.show', $event);
    }
}
