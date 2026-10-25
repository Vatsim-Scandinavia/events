<?php

namespace App\Staffing;

use App\Models\Event;
use App\Models\EventOccurrence;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something about who works which position when changed on these dates: a booking, the
 * positions or shifts, a roster, the dates themselves, or whether they're cancelled. Fired
 * once the change is committed, for Integrations to follow (see ADR 0004); the core itself
 * never listens.
 */
final class StaffingChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<int>  $occurrenceIds  Including dates that were deleted.
     */
    public function __construct(public array $occurrenceIds) {}

    public static function forOccurrence(EventOccurrence $occurrence): void
    {
        self::dispatch([$occurrence->id]);
    }

    public static function forEvent(Event $event): void
    {
        self::dispatch($event->occurrences()->pluck('id')->all());
    }
}
