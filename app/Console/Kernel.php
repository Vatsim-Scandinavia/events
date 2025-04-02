<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Notify webhook about upcoming event
        $schedule->command('events:notify')->everyMinute();

        // Cleanup webhook notifications for past events
        $schedule->command('notify:cleanup')->everyThirtyMinutes();

        // Cleanup old database records
        $schedule->command('database:cleanup')->daily();

        // Reset staffings
        $schedule->command('staffing:reset')->hourly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
