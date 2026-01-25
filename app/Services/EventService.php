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
    public function __construct(
        protected BannerUploadService $bannerUploadService
    )
    {}

    /**
     * Create a new event
     * 
     * @param array $data
     * @param User $user
     * @param UploadedFile|null $bannerFile
     * @return Event
     */
    public function createEvent(array $data, $user, $bannerFile = null): Event
    {
        if (!empty($data['recurrence_rule'])) {
            $this->validateRRule($data['recurrence_rule']);
        }

        return DB::transaction(function () use ($data, $user, $bannerFile) {
            $bannerPath = null;
            if ($bannerFile) {
                $bannerPath = $this->bannerUploadService->upload($bannerFile);
            }

            $event = Event::create([
                ...collect($data)->except('banner')->toArray(),
                'banner_path' => $bannerPath,
                'created_by' => $user->id,
            ]);

            $actor = auth()->user()?->vatsim_cid ?? 'System';
            Log::info('Event "' . $event->title . '" (' . $event->id . ') created by user: ' . $actor);

            return $event;
        });
    }

    /**
     * Update an event
     * 
     * @param Event $event
     * @param array $data
     * @param UploadedFile|null $bannerFile
     * @return Event
     */
    public function updateEvent(Event $event, array $data, $bannerFile = null): Event
    {
        if (!empty($data['recurrence_rule'])) {
            $this->validateRRule($data['recurrence_rule']);
        }

        return DB::transaction(function () use ($event, $data, $bannerFile) {
            if ($bannerFile) {
                if ($event->banner_path) {
                    $this->bannerUploadService->delete($event->banner_path);
                }

                $event->banner_path = $this->bannerUploadService->upload($bannerFile);
            }

            $event->update(collect($data)->except('banner')->toArray());

            $actor = auth()->user()?->vatsim_cid ?? 'System';
            Log::info('Event "' . $event->title . '" (' . $event->id . ') updated by user: ' . $actor);

            return $event;
        });
    }

    public function deleteEvent(Event $event): bool
    {
        return DB::transaction(function () use ($event) {
            if ($event->banner_path) {
                $this->bannerUploadService->delete($event->banner_path);
            }

            $actor = auth()->user()?->vatsim_cid ?? 'System';
            Log::info('Event "' . $event->title . '" (' . $event->id . ') deleted by user: ' . $actor);

            $event->delete();

            return true;
        });
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
            'event'             => $event,
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
    public function generateUpcomingInstances(Event $event, int $limit = 10): Collection
    {
        $duration = $event->start_datetime->diffInMinutes($event->end_datetime);
        $cancelledDates = $event->cancelled_occurrences ?? [];

        // Start with the base date
        $instances = collect([$event->start_datetime]);

        // Add recurring dates if they exist
        if (!empty($event->recurrence_rule)) {
            $rule = new \Recurr\Rule($event->recurrence_rule, $event->start_datetime->toDateTime(), null, 'UTC');
            $transformer = new \Recurr\Transformer\ArrayTransformer();
            
            foreach ($transformer->transform($rule) as $occurrence) {
                $occStart = \Illuminate\Support\Carbon::instance($occurrence->getStart());
                if (!$occStart->equalTo($event->start_datetime)) {
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
     */
    public function validateRRule(string $rruleString): bool
    {
        try {
            new Rule($rruleString);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}