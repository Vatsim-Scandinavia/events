<?php

namespace App\Services;

use Carbon\Carbon;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\ArrayTransformerConfig;
use Recurr\Transformer\Constraint\BetweenConstraint;
use Recurr\Transformer\TextTransformer;

class RecurringEventService
{
    /**
     * Validate an rrule string
     */
    public function validateRRule(string $rrule): bool
    {
        if (empty($rrule)) {
            return false;
        }

        try {
            new Rule($rrule);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Generate event instances from an rrule for a date range
     * Optionally filter cancelled occurrences
     */
    public function generateInstances(
        string $rrule,
        Carbon $startDate,
        Carbon $endDate,
        int $limit = 100,
        array $cancelledOccurrences = [],
        ?Carbon $rangeStart = null
    ): array {
        try {
            $rangeStart = $rangeStart ?? $startDate;
            $rule = new Rule($rrule, $startDate);
            $transformer = new ArrayTransformer(
                (new ArrayTransformerConfig)->setVirtualLimit(
                    max(732, $startDate->diffInHours($endDate) + $limit + 24)
                )
            );
            $constraint = new BetweenConstraint(
                $rangeStart->toDateTime(),
                $endDate->toDateTime(),
                true
            );

            $instances = $transformer->transform(
                $rule,
                $constraint,
                $rule->getCount() !== null
            );

            $results = [];
            foreach ($instances as $instance) {
                $instanceStart = Carbon::instance($instance->getStart());
                $instanceEnd = Carbon::instance($instance->getEnd());

                // Check if this occurrence is cancelled
                if ($this->containsOccurrence($cancelledOccurrences, $instanceStart)) {
                    continue; // Skip cancelled occurrences
                }

                // Only include instances within the requested range
                if ($instanceStart->between($rangeStart, $endDate)) {
                    $results[] = [
                        'start' => $instanceStart,
                        'end' => $instanceEnd,
                        'cancelled' => false,
                    ];
                }

                if (count($results) >= $limit) {
                    break;
                }
            }

            return $results;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Generate all instances including cancelled ones (for management)
     */
    public function generateAllInstances(
        string $rrule,
        Carbon $startDate,
        Carbon $endDate,
        int $limit = 100,
        array $cancelledOccurrences = []
    ): array {
        try {
            $rule = new Rule($rrule, $startDate);
            $transformer = new ArrayTransformer;

            $instances = $transformer->transform($rule, null, $limit);

            $results = [];
            foreach ($instances as $instance) {
                $instanceStart = Carbon::instance($instance->getStart());
                $instanceEnd = Carbon::instance($instance->getEnd());

                // Check if this occurrence is cancelled
                $isCancelled = $this->containsOccurrence($cancelledOccurrences, $instanceStart);

                // Only include instances within the requested range
                if ($instanceStart->lte($endDate)) {
                    $results[] = [
                        'start' => $instanceStart,
                        'end' => $instanceEnd,
                        'cancelled' => $isCancelled,
                    ];
                }
            }

            return $results;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get human-readable text for an rrule
     */
    public function getHumanReadable(string $rrule, string $locale = 'en'): string
    {
        try {
            $rule = new Rule($rrule);
            $textTransformer = new TextTransformer;

            return $textTransformer->transform($rule);
        } catch (\Exception $e) {
            return 'Invalid recurrence rule';
        }
    }

    /**
     * Parse rrule from frontend format to standard format
     */
    public function parseRRule(array $data): string
    {
        // This will be called from the frontend with structured data
        // and convert it to a proper RRULE string
        $parts = ['FREQ='.strtoupper($data['freq'])];

        if (isset($data['interval']) && $data['interval'] > 1) {
            $parts[] = 'INTERVAL='.$data['interval'];
        }

        if (isset($data['count'])) {
            $parts[] = 'COUNT='.$data['count'];
        }

        if (isset($data['until'])) {
            $until = Carbon::parse($data['until'])->format('Ymd\THis\Z');
            $parts[] = 'UNTIL='.$until;
        }

        if (isset($data['byDay']) && ! empty($data['byDay'])) {
            $parts[] = 'BYDAY='.implode(',', $data['byDay']);
        }

        if (isset($data['byMonthDay']) && ! empty($data['byMonthDay'])) {
            $parts[] = 'BYMONTHDAY='.implode(',', $data['byMonthDay']);
        }

        if (isset($data['byMonth']) && ! empty($data['byMonth'])) {
            $parts[] = 'BYMONTH='.implode(',', $data['byMonth']);
        }

        return implode(';', $parts);
    }

    private function containsOccurrence(array $occurrences, Carbon $target): bool
    {
        foreach ($occurrences as $occurrence) {
            try {
                if (Carbon::parse($occurrence)->equalTo($target)) {
                    return true;
                }
            } catch (\Exception) {
                continue;
            }
        }

        return false;
    }
}
