<?php

namespace App\Observers;

use App\Models\Event;
use App\Services\RecurrenceService;

class EventObserver
{
    public function __construct(private RecurrenceService $recurrenceService) {}

    /**
     * After an event is updated, regenerate future occurrences if the
     * recurrence rule changed. Occurrences are always kept in sync regardless
     * of draft/published status — visibility is controlled by the event status
     * at render time, not by the occurrence records themselves.
     */
    public function updated(Event $event): void
    {
        if (!$event->wasChanged('recurrence_rule')) {
            return;
        }

        $this->recurrenceService->pruneAndRegenerate($event);
    }
}
