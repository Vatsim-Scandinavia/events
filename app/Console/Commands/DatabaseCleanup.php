<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;

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
    protected $description = 'Cleanup the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Cleaning up the database...');

        // Delete events who have been soft deleted for month
        Event::onlyTrashed()->where('deleted_at', '<=', now()->subMonth())->forceDelete();

        $this->info('Database cleanup complete.');
    }
}
