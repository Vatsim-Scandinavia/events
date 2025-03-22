<?php

namespace App\Console\Commands;

use App\Helpers\StaffingHelper;
use App\Models\Staffing;
use Illuminate\Console\Command;

class ResetStaffing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staffing:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset staffing to the next event.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get all staffings
        $staffings = Staffing::all();

        $i = 0;
        foreach ($staffings as $staffing) {
            $event = $staffing->event;

            // Skip if event has not ended yet
            if (!$event || $event->end_date > now()) {
                continue;
            }

            // Reset staffing to the next event
            $response = StaffingHelper::resetStaffing($staffing);

            if (!$response) {
                $this->error('Failed to reset staffing: '.$staffing->id);
                continue;
            }

            try {
                $resp = StaffingHelper::updateDiscordMessage($staffing, true);
            } catch (\Exception $e) {
                $this->error('Failed to update Discord message: '.$staffing->id.' | Error: '.$e->getMessage());
                continue;
            }

            $i++;
            $this->info('Staffing reset successfully: ID = '.$staffing->id);
        }

        $this->info('Reset staffing for '.$i.' staffing(s).');
    }
}
