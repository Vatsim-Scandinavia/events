<?php

namespace App\Console\Commands;

use App\Models\Staffing;
use Illuminate\Console\Command;
use App\Services\StaffingService;
use App\Services\EventService;

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
    public function handle(StaffingService $service)
    {
        // Use the relationship name you confirmed earlier: 'instance'
        $staffings = Staffing::with('instance')->get();
        $count = 0;

        foreach ($staffings as $staffing) {
            // 1. Check if it needs a reset using the instance logic
            if ($service->needsReset($staffing)) {
                try {
                    // 2. This method handles everything: instance move, positions, and Discord
                    StaffingService::resetAndSync($staffing);
                    
                    $count++;
                    $this->info("Successfully reset and synced Staffing ID: {$staffing->id}");
                } catch (\Exception $e) {
                    $this->error("Failed to reset Staffing ID: {$staffing->id}. Error: " . $e->getMessage());
                }
            }
        }

        $this->info("Total staffings reset: $count");
    }
}
