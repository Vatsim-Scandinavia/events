<?php

namespace App\Http\Controllers;

use App\Actions\RecordAudit;
use App\Http\Requests\EventCollaborationRequest;
use App\Models\Event;
use App\Models\EventCollaboration;
use App\Models\Team;
use App\PermissionName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EventCollaborationController extends Controller
{
    public function store(EventCollaborationRequest $request, Event $event, RecordAudit $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $event, $audit): void {
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manageOwner', $event);
            Team::whereKey($request->integer('team_id'))->lockForUpdate()->firstOrFail();
            $before = $event->auditValues();
            $event->collaborations()->firstOrCreate(['team_id' => $request->integer('team_id')]);
            $audit->handle($event, 'updated', $before, $event->auditValues());
        });

        return back();
    }

    public function update(Request $request, Event $event, EventCollaboration $collaboration, RecordAudit $audit): RedirectResponse
    {
        abort_unless($collaboration->event_id === $event->id, 404);
        Gate::authorize(PermissionName::ManageEvents, $collaboration->team);
        DB::transaction(function () use ($event, $collaboration, $audit): void {
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $collaboration = $event->collaborations()->whereKey($collaboration->id)->lockForUpdate()->firstOrFail();
            Gate::authorize(PermissionName::ManageEvents, $collaboration->team);
            $before = $event->auditValues();
            if ($collaboration->accepted_at === null) {
                $collaboration->update(['accepted_at' => now()]);
            }
            $audit->handle($event, 'updated', $before, $event->auditValues());
        });

        return to_route('events.show', $event);
    }

    public function destroy(Event $event, EventCollaboration $collaboration, RecordAudit $audit): RedirectResponse
    {
        abort_unless($collaboration->event_id === $event->id, 404);
        Gate::authorize('manageOwner', $event);
        DB::transaction(function () use ($event, $collaboration, $audit): void {
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manageOwner', $event);
            $before = $event->auditValues();
            $event->collaborations()->whereKey($collaboration->id)->delete();
            $audit->handle($event, 'updated', $before, $event->auditValues());
        });

        return back();
    }
}
