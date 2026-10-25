<?php

namespace App\Integrations;

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
}
