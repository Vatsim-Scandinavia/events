<?php

namespace App\Integrations;

use Illuminate\Console\Scheduling\Schedule;

/**
 * One job an Integration can do, switched on separately, such as syncing the controller
 * roster. A feature that pulls data on a schedule also implements Syncable; one that decides
 * who holds the Controller role also implements App\Auth\ControllerSource.
 */
abstract class Feature
{
    /**
     * Identifies it within its Integration, e.g. "roster". Never change it once shipped.
     */
    abstract public function key(): string;

    abstract public function name(): string;

    abstract public function description(): string;

    /**
     * Features, in any Integration, that can't be on at the same time as this one, as
     * "integration-key.feature-key". Controller sources always conflict with each other.
     *
     * @return list<string>
     */
    public function conflictsWith(): array
    {
        return [];
    }

    /**
     * Called when an admin switches it off, or its Integration, so it can forget what it kept
     * for doing its job. Whatever it gave stays: a Controller source's roles become manual.
     */
    public function switchedOff(): void {}

    /**
     * Called once as the app boots, whether or not it's on, to listen for what the core
     * announces (such as App\Staffing\StaffingChanged). Listeners must check it's on first,
     * and never change the core's records.
     */
    public function boot(): void {}

    /**
     * Called as the scheduler is set up, for work it does on its own schedule that isn't a
     * sync (see Syncable), so isn't recorded as a run. Tasks must check it's on first.
     */
    public function scheduled(Schedule $schedule): void {}

    /**
     * Things it couldn't do that admins should look at, one line each, shown on the
     * Integrations page.
     *
     * @return list<string>
     */
    public function needsAttention(): array
    {
        return [];
    }
}
