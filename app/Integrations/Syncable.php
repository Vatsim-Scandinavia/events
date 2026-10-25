<?php

namespace App\Integrations;

use Illuminate\Console\Scheduling\Event;

/**
 * A feature that pulls data from its external system on a schedule and when an admin asks.
 *
 * Each sync runs in a database transaction and is recorded as a run. If it throws, nothing it
 * changed is kept, so fetch everything before writing anything.
 */
interface Syncable
{
    /**
     * How often it runs, e.g. $event->hourly().
     */
    public function schedule(Event $event): void;

    public function sync(SyncContext $context): SyncResult;
}
