<?php

namespace App\Integrations;

use App\Enums\IntegrationRunStatus;

/**
 * How a sync went: what it changed, or why it held its changes back for an Administrator.
 */
final readonly class SyncResult
{
    /**
     * @param  list<string>  $warnings  Things admins should look at, that didn't stop the sync.
     */
    private function __construct(
        public IntegrationRunStatus $status,
        public int $added = 0,
        public int $updated = 0,
        public int $removed = 0,
        public ?string $message = null,
        public array $warnings = [],
    ) {}

    /**
     * @param  list<string>  $warnings
     */
    public static function succeeded(int $added = 0, int $updated = 0, int $removed = 0, array $warnings = []): self
    {
        return new self(IntegrationRunStatus::Succeeded, $added, $updated, $removed, warnings: $warnings);
    }

    /**
     * Nothing was changed. $reason tells admins what they'd be approving.
     *
     * @param  list<string>  $warnings
     */
    public static function held(string $reason, array $warnings = []): self
    {
        return new self(IntegrationRunStatus::Held, message: $reason, warnings: $warnings);
    }
}
