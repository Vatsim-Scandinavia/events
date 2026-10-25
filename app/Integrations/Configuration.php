<?php

namespace App\Integrations;

/**
 * An Integration's settings as they apply (the environment's values, then what admins saved),
 * with the features switched on and the removal limit.
 */
final readonly class Configuration
{
    /**
     * @param  array<string, mixed>  $settings
     * @param  list<string>  $features
     */
    public function __construct(
        public array $settings,
        public array $features,
        public int $removalLimit,
    ) {}

    public function get(string $key): mixed
    {
        return $this->settings[$key] ?? null;
    }

    public function hasFeature(string $key): bool
    {
        return in_array($key, $this->features, true);
    }
}
