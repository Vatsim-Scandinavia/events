<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Scheduled command to permanently delete old soft-deleted records.
 *
 * Removes events that have been soft-deleted for more than 30 days
 * to keep the database clean. Also deletes associated banner images.
 */
class DatabaseCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'database:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permanently deletes soft-deleted events older than 30 days and their banner images';

    /**
     * Execute the console command.
     *
     * Finds soft-deleted events from more than 30 days ago and permanently
     * removes them from the database. Also deletes banner image files
     * to free up storage space.
     *
     * @return int Command exit code (0 = success)
     */
    public function handle()
    {
        $this->info('Cleaning up the database...');

        $events = Event::onlyTrashed()
            ->where('deleted_at', '<=', now()->subMonth())
            ->get();

        $count = 0;
        foreach ($events as $event) {
            if ($event->image) {
                Storage::disk('public')->delete('banners/' . $event->image);
            }
            $event->forceDelete();
            $count++;
        }

        $this->info("Database cleanup complete. Permanently removed {$count} events.");
        return 0;
    }
}
