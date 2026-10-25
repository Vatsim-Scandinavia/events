<?php

namespace App\Console\Commands;

use App\Enums\RepeatInterval;
use App\Models\Event;
use App\Staffing\EventSchedule;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Events that repeat indefinitely only have their dates created a few months ahead.
 * This adds the dates that have come within reach since, and runs every day.
 */
#[Signature('events:extend-repeats')]
#[Description('Add upcoming dates to events that repeat indefinitely')]
class ExtendRepeatingEvents extends Command
{
    public function handle(EventSchedule $schedule): void
    {
        $events = Event::query()
            ->where('repeats', '!=', RepeatInterval::None)
            ->whereNull('repeat_count')
            ->whereNull('repeat_until')
            ->whereNull('cancelled_at')
            ->get();

        foreach ($events as $event) {
            try {
                $planned = $schedule->plan($event, $event->starts_at, $event->ends_at, $event->timezone, $event->repeats, null, null);
            } catch (ValidationException $exception) {
                // A rule that no longer fits the event (e.g. shifts longer than it) waits for staff to fix it.
                $this->warn("{$event->name}: ".collect($exception->errors())->flatten()->first());

                continue;
            }

            $added = $planned->whereNull('id')->count();

            if ($added > 0) {
                DB::transaction(fn () => $schedule->save($event, $planned));
                $this->info("{$event->name}: added {$added} ".str('date')->plural($added).'.');
            }
        }
    }
}
