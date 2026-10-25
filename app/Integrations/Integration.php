<?php

namespace App\Integrations;

/**
 * A connection to one external system that Administrators can switch on for the whole Hub,
 * such as the vACC's training system. It owns the system's settings and connection test,
 * and offers Integration features that are switched on separately.
 *
 * Register each one in config/integrations.php. The Hub must work fully with every
 * Integration switched off, so nothing outside an extension point may ask whether one is on.
 */
abstract class Integration
{
    /**
     * Identifies it in storage and URLs, e.g. "control-center". Never change it once shipped.
     */
    abstract public function key(): string;

    abstract public function name(): string;

    /**
     * What it connects the Hub to, as admins see it on the Integrations page.
     */
    abstract public function description(): string;

    /**
     * @return list<Setting>
     */
    abstract public function settings(): array;

    /**
     * @return list<Feature>
     */
    abstract public function features(): array;

    /**
     * Check the external system can be reached with these settings, for the features they
     * switch on. An Integration can't be switched on until this passes.
     *
     * @throws ConnectionFailed
     */
    abstract public function testConnection(Configuration $configuration): void;

    public function feature(string $key): ?Feature
    {
        return collect($this->features())->first(fn (Feature $feature) => $feature->key() === $key);
    }

    /**
     * Whether any of its features syncs on a schedule, and so has runs and a removal limit.
     */
    public function syncs(): bool
    {
        return collect($this->features())->contains(fn (Feature $feature) => $feature instanceof Syncable);
    }
}
