<?php

namespace App\Actions;

use App\Models\Airport;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventRoster;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class RecordAudit
{
    /**
     * Persist alongside the subject's change, inside the same transaction.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function handle(Team|User|Event|Airport|EventRoster $subject, string $event, array $before, array $after, string $source = 'manual', ?User $actor = null): void
    {
        [$type, $label, $fields] = match (true) {
            $subject instanceof Team => ['fir', $subject->code.' — '.$subject->name, ['code', 'name']],
            $subject instanceof Airport => ['airport', $subject->icao.' — '.$subject->name, ['icao', 'name', 'country']],
            $subject instanceof EventRoster => ['roster', $subject->event->title, ['event_id', 'mode', 'is_open', 'shifts', 'positions', 'bookings', 'interests']],
            $subject instanceof Event => ['event', $subject->title, ['owner_team_id', 'title', 'short_description', 'description', 'timezone', 'local_start', 'local_end', 'recurrence', 'recurrence_interval', 'monthly_week', 'recurrence_until', 'roster_enabled', 'status', 'published_at', 'banner_path', 'cancellation_reason', 'airports', 'cancellations', 'collaborations']],
            default => ['user', $subject->name_full, ['name_full', 'email', 'controller_rating', 'division', 'subdivision', 'oauth_provider', 'roles']],
        };
        $before = Arr::only($before, $fields);
        $after = Arr::only($after, $fields);
        $changed = array_filter(array_unique([...array_keys($before), ...array_keys($after)]),
            fn (string $field): bool => ($before[$field] ?? null) !== ($after[$field] ?? null));

        if ($changed === []) {
            return;
        }

        $actor ??= Auth::user();

        AuditLog::create([
            'actor_cid' => $actor?->cid,
            'actor_name' => $actor?->name_full,
            'subject_type' => $type,
            'subject_id' => $subject->getKey(),
            'subject_label' => $label,
            'event' => $event,
            'source' => $source,
            'old_values' => Arr::only($before, $changed),
            'new_values' => Arr::only($after, $changed),
            'created_at' => now(),
        ]);
    }
}
