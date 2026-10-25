<?php

namespace App\Integrations;

/**
 * What a sync gets to work with: its Integration's configuration, and whether an Administrator
 * approved removing more than the limit allows.
 */
final readonly class SyncContext
{
    public function __construct(
        public Configuration $configuration,
        public bool $approved = false,
    ) {}

    /**
     * Whether the sync may remove $removing of the $existing things it manages. Removing more
     * than the removal limit's share needs an Administrator's approval, so a bug upstream can't
     * empty the Hub. Return SyncResult::held() when this is false.
     */
    public function mayRemove(int $removing, int $existing): bool
    {
        if ($this->approved || $removing === 0 || $existing === 0) {
            return true;
        }

        return $removing * 100 <= $existing * $this->configuration->removalLimit;
    }
}
