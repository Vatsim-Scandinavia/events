<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventOccurrence;
use Illuminate\Validation\ValidationException;

/**
 * Calling off a whole event or a single date, and taking that back. Nothing is
 * deleted: bookings, interest and rosters stay so a restored date picks up where it was.
 */
final class EventCancellations
{
    /**
     * @throws ValidationException when every date is already over
     */
    public function cancelEvent(Event $event): void
    {
        if ($event->occurrences->every(fn (EventOccurrence $occurrence) => $occurrence->hasEnded())) {
            $this->fail('All of this event’s dates are already over.');
        }

        $event->update(['cancelled_at' => $event->cancelled_at ?? now()]);
    }

    public function restoreEvent(Event $event): void
    {
        $event->update(['cancelled_at' => null]);
    }

    /**
     * @throws ValidationException when the date has started
     */
    public function cancelOccurrence(EventOccurrence $occurrence): void
    {
        if ($occurrence->hasStarted()) {
            $this->fail('This date has already started, so it can’t be cancelled.');
        }

        $occurrence->update(['cancelled_at' => $occurrence->cancelled_at ?? now()]);
    }

    /**
     * @throws ValidationException when the date has started or the whole event is cancelled
     */
    public function restoreOccurrence(EventOccurrence $occurrence): void
    {
        if ($occurrence->hasStarted()) {
            $this->fail('This date has already started, so it can’t be restored.');
        }

        if ($occurrence->event->isCancelled()) {
            $this->fail('The whole event is cancelled. Restore the event first.');
        }

        $occurrence->update(['cancelled_at' => null]);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['cancel' => $message]);
    }
}
