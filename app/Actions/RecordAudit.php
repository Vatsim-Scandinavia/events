<?php

namespace App\Actions;

use App\Models\AuditLog;
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
    public function handle(Team|User $subject, string $event, array $before, array $after, string $source = 'manual', ?User $actor = null): void
    {
        $fields = $subject instanceof Team
            ? ['code', 'name']
            : ['name_full', 'email', 'controller_rating', 'division', 'subdivision', 'oauth_provider', 'roles'];
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
            'subject_type' => $subject instanceof Team ? 'fir' : 'user',
            'subject_id' => $subject->getKey(),
            'subject_label' => $subject instanceof Team ? $subject->code.' — '.$subject->name : $subject->name_full,
            'event' => $event,
            'source' => $source,
            'old_values' => Arr::only($before, $changed),
            'new_values' => Arr::only($after, $changed),
            'created_at' => now(),
        ]);
    }
}
