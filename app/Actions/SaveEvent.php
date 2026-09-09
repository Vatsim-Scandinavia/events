<?php

namespace App\Actions;

use App\Http\Requests\EventRequest;
use App\Models\Event;
use App\Models\Team;
use App\PermissionName;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class SaveEvent
{
    public function __construct(private EventSchedule $schedule, private RecordAudit $audit) {}

    public function handle(EventRequest $request, ?Event $event = null): Event
    {
        $data = $request->safe()->except(['banner', 'remove_banner', 'airport_ids']);
        $data['starts_at'] = $this->schedule->resolveLocal($data['local_start'], $data['timezone']);
        $data['ends_at'] = $this->schedule->resolveLocal($data['local_end'], $data['timezone']);
        if ($data['recurrence'] === 'none') {
            $data['recurrence_interval'] = 1;
            $data['recurrence_until'] = null;
        }
        $data['monthly_week'] = $data['recurrence'] === 'monthly' ? $data['monthly_week'] : null;
        $newPath = null;
        $oldPath = null;

        try {
            if ($request->hasFile('banner')) {
                $newPath = $request->file('banner')->store('event-banners', 'local');
                if ($newPath === false) {
                    throw ValidationException::withMessages(['banner' => 'The banner could not be saved. Please try again.']);
                }
            }

            $saved = DB::transaction(function () use ($request, $event, $data, $newPath, &$oldPath): Event {
                $event = $event === null ? new Event : Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
                if ($event->exists) {
                    Gate::authorize('update', $event);
                    if ($event->status === 'cancelled') {
                        throw ValidationException::withMessages(['event' => 'Cancelled events cannot be edited.']);
                    }
                } else {
                    $owner = Team::whereKey($data['owner_team_id'])->lockForUpdate()->firstOrFail();
                    Gate::authorize(PermissionName::ManageEvents, $owner);
                }

                $before = $event->exists ? $event->auditValues() : [];
                if ($event->exists && ($event->cancellations()->exists() || $event->rosters()->exists())) {
                    foreach (['timezone', 'local_start', 'local_end', 'recurrence', 'recurrence_interval', 'monthly_week', 'recurrence_until'] as $field) {
                        if ((string) ($before[$field] ?? '') !== (string) ($data[$field] ?? '')) {
                            throw ValidationException::withMessages(['recurrence' => 'This event has rosters or cancelled occurrences. Keep its schedule and create a new event for a different schedule.']);
                        }
                    }
                }

                $oldPath = $event->banner_path;
                $event->fill(Arr::except($data, ['owner_team_id']));
                if (! $event->exists) {
                    $event->owner_team_id = $data['owner_team_id'];
                    $event->status = 'draft';
                }
                if (is_string($newPath)) {
                    $event->banner_path = $newPath;
                } elseif ($request->boolean('remove_banner')) {
                    $event->banner_path = null;
                }
                $event->save();
                $event->airports()->sync($request->validated('airport_ids'));
                $this->audit->handle($event, $before === [] ? 'created' : 'updated', $before, $event->auditValues());

                return $event;
            });
        } catch (Throwable $exception) {
            if (is_string($newPath)) {
                Storage::disk('local')->delete($newPath);
            }
            throw $exception;
        }

        if ($oldPath !== null && $oldPath !== $saved->banner_path) {
            Storage::disk('local')->delete($oldPath);
        }

        return $saved;
    }
}
