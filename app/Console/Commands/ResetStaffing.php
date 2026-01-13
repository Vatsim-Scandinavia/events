<?php

namespace App\Console\Commands;

use App\Models\Staffing;
use Illuminate\Console\Command;
use App\Services\StaffingService;
use App\Services\EventService;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled command to automatically reset staffings to next event instance.
 *
 * Runs periodically to check if staffings are attached to past event instances.
 * When found, moves them to the next future instance, clears bookings, and updates Discord.
 */
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
    protected $description = 'Automatically resets staffings to the next event instance when current one has ended';

    /**
     * Execute the console command.
     *
     * Iterates through all staffings and checks if their current instance has ended.
     * If ended, moves staffing to next instance, clears all bookings, and notifies Discord.
     *
     * @param StaffingService $service
     * @return int Command exit code (0 = success)
     */
    public function handle(StaffingService $service)
    {
        $staffings = Staffing::with('instance')->get();
        $count = 0;

        foreach ($staffings as $staffing) {
            if ($service->needsReset($staffing)) {
                try {
                    StaffingService::resetAndSync($staffing);
                    
                    $count++;
                    $this->info("Successfully reset and synced Staffing ID: {$staffing->id}");
                } catch (\Exception $e) {
                    $this->error("Failed to reset Staffing ID: {$staffing->id}. Error: " . $e->getMessage());
                    Log::error('Staffing reset failed in scheduled command', [
                        'staffing_id' => $staffing->id,
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
        }

        $this->info("Total staffings reset: $count");
        return 0;
    }
}
