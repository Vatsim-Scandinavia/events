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

        // Reset staffing to the next event
        foreach ($staffings as $staffing) {
            $response = StaffingHelper::resetStaffing($staffing);

            if (!$response) {
                $this->error('Failed to reset staffing: '.$staffing->id);
                continue;
            }

            $resp = StaffingHelper::updateDiscordMessage($staffing);

            if (!$resp) {
                return $this->error('Failed to update Discord message: '.$staffing->id);
            }

            $this->info('Staffing reset successfully: '.$staffing->id);
        }

        $this->info('Reset staffing for '.$staffings->count().' staffings.');
    }
}
