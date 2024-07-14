<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;

class CheckEventRecurrences extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'events:check-recurrences';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for missing or extra recurrences for all events';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $events = Event::whereNotNull('recurrence_interval')
                       ->whereNotNull('recurrence_unit')
                       ->get();

        foreach ($events as $event) {
            $recurrenceCheck = $event->checkRecurrences();

            $missingRecurrences = $recurrenceCheck['missing_recurrences'];
            $extraRecurrences = $recurrenceCheck['extra_recurrences'];
            
            if (!empty($missingRecurrences)) {
                $this->info("Event ID {$event->id} has missing recurrences:");
                foreach ($missingRecurrences as $missing) {
                    $this->info("Start: " . $missing['start_date'] . ", End: " . $missing['end_date']);
                }
            }

            if(!empty($extraRecurrences)) {
                $this->info("Event ID {$event->id} has extra recurrences:");
                foreach ($extraRecurrences as $extra) {
                    $this->info("ID: " . $extra->id . ", Start: " . $extra->start_date . ", End: " . $extra->end_date);
                }
            }
        }

        return 0;
    }
}
