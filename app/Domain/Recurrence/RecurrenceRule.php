<?php

namespace App\Domain\Recurrence;

use Carbon\Carbon;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\Constraint\BetweenConstraint;
use InvalidArgumentException;

class RecurrenceRule
{
    protected Rule $rule;

    public function __construct(
        protected string $rrule,
        protected Carbon $start,
        protected string $timezone = 'UTC'
    ) {
        $this->initialize();
    }

    /**
     * Initialize the Recurr\Rule instance from the provided RRULE string, start time, and timezone.
     * @throws InvalidArgumentException if the RRULE string is invalid or if the start time cannot be parsed
     */
    protected function initialize(): void
    {
        try {
            $this->rule = new Rule(
                $this->rrule,
                $this->start->copy()->setTimezone($this->timezone)->toDateTime(),
                null,
                $this->timezone
            );
        } catch (\Exception $e) {
            throw new InvalidArgumentException(
                "Invalid recurrence rule: {$e->getMessage()}",
            );
        }
    }

    public static function validate(string $rule): void
    {
        try {
            new Rule($rule);
        } catch (\Exception $e) {
            throw new InvalidArgumentException(
                "Invalid recurrence rule: {$e->getMessage()}",
            );
        }
    }

    /**
     * Returns occurrences between the given range in the rule's timezone.
     * @param Carbon $from Start of the range (inclusive)
     * @param Carbon $to End of the range (inclusive)
     * @return Carbon[] Array of Carbon instances representing occurrence start times in the rule's timezone
     */
    public function occurrencesBetween(Carbon $from, Carbon $to): array
    {
        $collection = (new ArrayTransformer())->transform(
            $this->rule,
            new BetweenConstraint(
                $from->copy()->setTimezone($this->timezone)->toDateTime(),
                $to->copy()->setTimezone($this->timezone)->toDateTime(),
                true
            )
        );

        return collect($collection)
            ->map(fn ($r) => Carbon::instance($r->getStart())
                ->setTimezone($this->timezone))
            ->values()
            ->all();
    }

    /**
     * Returns occurrences between the given range in UTC timezone.
     */
    public function occurrencesBetweenUtc(Carbon $from, Carbon $to): array
    {
        return collect($this->occurrencesBetween($from, $to))
            ->map(fn ($c) => $c->copy()->utc())
            ->values()
            ->all();
    }

    /**
     * Returns future occurrences starting from now up to a specified number of months ahead, in the rule's timezone.
     * @param int $monthsAhead Number of months ahead to look for future occurrences
     * @return Carbon[] Array of Carbon instances representing future occurrence start times in the rule's timezone
     */
    public function futureOccurrences(int $monthsAhead = 3): array
    {
        return $this->occurrencesBetween(
            now(),
            now()->addMonths($monthsAhead)
        );
    }

    /**
     * Returns the RRULE string representation of the recurrence rule.
     * @return string The RRULE string
     */
    public function toString(): string
    {
        return $this->rrule;
    }
}