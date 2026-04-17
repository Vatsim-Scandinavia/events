<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\RecurrenceService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateEventOccurrences implements ShouldQueue
{
    use Queueable;

    public function handle(RecurrenceService $recurrenceService): void
    {
        $horizon = now()->addMonths(RecurrenceService::HORIZON_MONTHS);

        Event::where('status', 'published')
            ->whereNotNull('recurrence_rule')
            ->each(fn(Event $event) => $recurrenceService->generateFor($event, $horizon));
    }
}
