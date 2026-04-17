<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventOccurrence;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Domain\Recurrence\OccurrenceGenerator;
use App\Domain\Recurrence\RecurrenceRule;
use InvalidArgumentException;

class RecurrenceService
{
    /**
     * How many months ahead to generate occurrences for.
     */
    public const HORIZON_MONTHS = 3;

    public function __construct(
        private OccurrenceGenerator $occurrenceGenerator = new OccurrenceGenerator(),
    ) {}

    /**
     * Generate all missing future occurrences for a single event up to the horizon.
     *
     * On update, call pruneAndRegenerate() instead so stale future occurrences
     * are replaced before new ones are inserted.
     */
    public function generate(Event $event): void
    {
        if (!$event->recurrence_rule) {
            return;
        }

        $this->generateFor($event, now()->addMonths(self::HORIZON_MONTHS));
    }

    /**
     * Prune all future scheduled occurrences and regenerate from scratch.
     * Used when the recurrence rule or start time changes on an existing event.
     */
    public function pruneAndRegenerate(Event $event): void
    {
        if (!$event->recurrence_rule) {
            // Rule was removed — delete all generated future occurrences, keeping
            // only the original single (template) occurrence.
            $template = $event->occurrences()->orderBy('start_time')->first();
            $event->occurrences()
                ->where('start_time', '>=', now())
                ->when($template, fn($q) => $q->where('id', '!=', $template->id))
                ->delete();
            return;
        }

        [$templateStart, $durationSeconds] = $this->resolveTemplate($event) ?? [null, null];

        if (!$templateStart) {
            return;
        }

        $event->occurrences()
            ->where('start_time', '>=', now())
            ->delete();

        $this->generateFromAnchor($event, now()->addMonths(self::HORIZON_MONTHS), $templateStart, $durationSeconds);
    }

    public function generateFor(Event $event, Carbon $horizon): void
    {
        [$templateStart, $durationSeconds] = $this->resolveTemplate($event) ?? [null, null];

        if (!$templateStart) {
            return;
        }

        $this->generateFromAnchor($event, $horizon, $templateStart, $durationSeconds);
    }

    private function generateFromAnchor(Event $event, Carbon $horizon, Carbon $templateStart, int $durationSeconds): void
    {
        $tz = $event->timezone ?: 'UTC';

        try {
            $rule = new RecurrenceRule($event->recurrence_rule, $templateStart, $tz);
        } catch (InvalidArgumentException $e) {
            Log::error("RecurrenceService: Invalid recurrence rule for Event {$event->id}: {$e->getMessage()}");
            return;
        }

        $existingStartTimes = $event->occurrences()
            ->where('start_time', '>=', now())
            ->pluck('start_time')
            ->map(fn($t) => Carbon::parse($t, 'UTC')->format('Y-m-d H:i:s'))
            ->flip();

        $newOccurrences = collect($this->occurrenceGenerator->generate($rule, now(), $horizon, $durationSeconds))
            ->reject(fn($occ) => $existingStartTimes->has($occ['start_time']))
            ->map(fn($occ) => array_merge($occ, [
                'event_id'    => $event->id,
                'notified_at' => null,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]))
            ->values()
            ->all();

        if (!empty($newOccurrences)) {
            EventOccurrence::insert($newOccurrences);
            Log::info("RecurrenceService: Created " . count($newOccurrences) . " occurrence(s) for Event {$event->id} ({$event->title}).");
        }
    }

    /**
     * Resolve the timing template (start time + duration) from the earliest occurrence of an event.
     * Returns null if no occurrences exist, after logging a warning.
     */
    private function resolveTemplate(Event $event): ?array
    {
        $template = $event->occurrences()->orderBy('start_time')->first();

        if (!$template) {
            Log::warning("RecurrenceService: Event {$event->id} has no occurrences to use as template, skipping.");
            return null;
        }

        $templateStart   = Carbon::parse($template->start_time, 'UTC');
        $durationSeconds = $templateStart->diffInSeconds(Carbon::parse($template->end_time, 'UTC'));

        return [$templateStart, $durationSeconds];
    }
}
