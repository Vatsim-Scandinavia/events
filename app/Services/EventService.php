<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Collection;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\Constraint\AfterConstraint;

class EventService
{
    public function __construct(protected BannerUploadService $bannerUploadService) {}

    /**
     * Get a lightweight summary of an event for list views
     * 
     * @param Event $event
     * @return array
     */
    public function getEventSummary(Event $event): array
    {
        $start = $event->start_datetime;

        $allInstances = $this->generateUpcomingInstances($event, 5);

        $nextActive = $allInstances->first(function ($occurrence) {
            return !$occurrence['cancelled'] && \Illuminate\Support\Carbon::parse($occurrence['end'])->isAfter(now());
        });

        return [
            'id'                => $event->id,
            'title'             => $event->title,
            'display_datetime'  => $nextActive ? $nextActive['start'] : $start?->toISOString(),
            'banner_url'        => $event->banner_path ? $this->bannerUploadService->getUrl($event->banner_path) : null,
            'recurrence_rule'   => $event->recurrence_rule,
        ];
    }

    /**
     * Get the details of an event
     * 
     * @param Event $event
     * @param bool $fullSchedule
     *     false: 5 instances
     *     true: 50 instances
     * @return array
     */
    public function getEventDetails(Event $event, bool $fullSchedule = false): array
    {
        $start = $event->start_datetime;

        $allInstances = $this->generateUpcomingInstances($event, $fullSchedule ? 50 : 5);

        $nextActive = $allInstances->first(function ($occ) {
            return !$occ['cancelled'] && \Illuminate\Support\Carbon::parse($occ['end'])->isAfter(now());
        });

        $followingInstances = $allInstances->reject(function ($occ) use ($nextActive) {
            return $nextActive && $occ['start'] === $nextActive['start'];
        });

        if (!$fullSchedule) {
            $followingInstances = $followingInstances->take(3);
        }

        return [
            'id'                => $event->id,
            'title'             => $event->title,
            'short_description' => $event->short_description,
            'long_description'  => $event->long_description,
            'start_datetime'    => $start?->toISOString(),
            'end_datetime'      => $event->end_datetime?->toISOString(),
            'featured_airports' => $event->featured_airports ?? [],
            'display_datetime'  => $nextActive ? $nextActive['start'] : $start?->toISOString(),
            'next_active_end'   => $nextActive ? $nextActive['end'] : $event->end_datetime?->toISOString(),
            'recurrence_rule'   => $event->recurrence_rule,
            'banner_url'        => $event->banner_path ? $this->bannerUploadService->getUrl($event->banner_path) : null,
            'calendar'          => $event->calendar,
            'creator'           => $event->creator,
            'staffings'         => $event->staffings()->with('positions')->get(),
            'instances'         => $followingInstances->values(),
        ];
    }

    /**
     * Get the management data for a recurring event
     * 
     * @param Event $event
     * @return array
     */
    public function getManagementData(Event $event): array
    {
        $event->load('calendar');

        $occurrences = $this->generateUpcomingInstances(
            $event,
            10
        );

        return [
            'event' => $event,
            'occurrences' => $occurrences,
        ];
    }

    /**
     * Generate upcoming instances for a recurring event
     * 
     * @param Event $event
     * @param int $limit
     * @return Collection
     */
    public function generateUpcomingInstances(Event $event, int $limit = 10, ?\Carbon\Carbon $startDate = null): Collection
    {
        $startDate = $startDate ?? now(); // Default to now if not provided
        $duration = $event->start_datetime->diffInMinutes($event->end_datetime);
        $cancelledDates = $event->cancelled_occurrences ?? [];

        $instances = collect();

        // Check if the base event start date matches our window
        if ($event->start_datetime->isAfter($startDate) || $event->start_datetime->isSameAs('minute', $startDate)) {
            $instances->push($event->start_datetime);
        }

        if (!empty($event->recurrence_rule)) {
            $rule = new Rule($event->recurrence_rule, $event->start_datetime->toDateTime(), null, 'UTC');
            $transformer = new ArrayTransformer();

            // Use the passed in startDate (e.g., 24 hours ago) instead of hardcoded 'now'
            $constraint = new AfterConstraint($startDate->toDateTime(), false);

            foreach ($transformer->transform($rule, $constraint) as $occurrence) {
                $occStart = \Illuminate\Support\Carbon::instance($occurrence->getStart());
                if (!$occStart->equalTo($event->start_datetime) && ($occStart->isAfter($startDate) || $occStart->isSameAs('minute', $startDate))) {
                    $instances->push($occStart);
                }
            }
        }

        return $instances
            ->sortBy->timestamp
            ->take($limit)
            ->map(function ($start) use ($duration, $cancelledDates) {
                $isoStart = $start->toISOString();
                return [
                    'start'     => $isoStart,
                    'end'       => $start->copy()->addMinutes($duration)->toISOString(),
                    'cancelled' => in_array($isoStart, $cancelledDates),
                ];
            })
            ->values();
    }

    /**
     * Toggle the cancellation status of an occurrence
     * 
     * @param Event $event
     * @param string $date
     * @param bool $cancel
     * @return void
     */
    public function toggleOccurrence(Event $event, string $date, bool $cancel = true): void
    {
        $formattedDate = \Illuminate\Support\Carbon::parse($date)->toISOString();

        $cancelled = $event->cancelled_occurrences ?? [];

        if ($cancel) {
            if (!in_array($formattedDate, $cancelled)) {
                $cancelled[] = $formattedDate;
            }
        } else {
            $cancelled = array_diff($cancelled, [$formattedDate]);
        }

        DB::transaction(function () use ($event, $cancelled) {
            $event->update(['cancelled_occurrences' => array_values($cancelled)]);

            $actor = auth()->user()?->vatsim_cid ?? 'System';
            Log::info("Occurrence toggled for event {$event->id} by {$actor}");
        });
    }

    /**
     * Validate a recurrence rule (RFC 5545)
     * 
     * @param string $rrule
     * @return void
     * @throws ValidationException
     */
    public function validateRRule(string $rruleString): void
    {
        try {
            new Rule($rruleString);
        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'recurrence_rule' => ['The recurrence rule is invalid: ' . $e->getMessage()],
            ]);
        }
    }

    /**
     * Get the last ended occurrence of a recurring event
     * 
     * @param Event $event
     * @return array|null
     */
    public function getLastEndedOccurrence(Event $event): ?array
    {
        $duration = $event->start_datetime->diffInMinutes($event->end_datetime);
        $lastStart = null;
        if ($event->start_datetime->isPast()) {
            $lastStart = $event->start_datetime;
        }

        if (!empty($event->recurrence_rule)) {
            $rule = new Rule($event->recurrence_rule, $event->start_datetime->toDateTime(), null, 'UTC');
            $transformer = new ArrayTransformer();

            $constraint = new AfterConstraint($event->start_datetime->toDateTime(), true);
            $occurrences = $transformer->transform($rule, $constraint);

            foreach ($occurrences as $occurrence) {
                $occStart = \Illuminate\Support\Carbon::instance($occurrence->getStart());

                if ($occStart->isPast()) {
                    if (!$lastStart || $occStart->isAfter($lastStart)) {
                        $lastStart = $occStart;
                    }
                } else {
                    break;
                }
            }
        }

        if (!$lastStart) return null;

        return [
            'start' => $lastStart->toISOString(),
            'end'   => $lastStart->copy()->addMinutes($duration)->toISOString(),
        ];
    }
}
