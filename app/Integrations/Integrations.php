<?php

namespace App\Integrations;

use App\Auth\ControllerSource;
use App\Models\IntegrationSetting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;

/**
 * The Integrations the Hub offers, and how admins have set each one up.
 *
 * A setting comes from the server's environment when it is set there, which locks it.
 * Otherwise it is whatever an admin saved on the Integrations page.
 */
final class Integrations
{
    /**
     * Every registered Integration, by key.
     *
     * @return Collection<string, Integration>
     */
    public function all(): Collection
    {
        return collect(config('integrations.registered'))
            ->map(fn (string $class) => app($class))
            ->keyBy(fn (Integration $integration) => $integration->key());
    }

    public function find(string $key): ?Integration
    {
        return $this->all()->get($key);
    }

    public function isEnabled(Integration $integration): bool
    {
        return $this->stored($integration)->enabled;
    }

    /**
     * Whether the feature is doing its job: switched on, in an Integration that is too.
     */
    public function isOn(Integration $integration, Feature $feature): bool
    {
        return $this->isEnabled($integration) && in_array($feature->key(), $this->stored($integration)->features ?? [], true);
    }

    /**
     * The feature in charge of the Controller role, or null when the hub's own rule is.
     */
    public function controllerSource(): ?ControllerSource
    {
        foreach ($this->all() as $integration) {
            foreach ($integration->features() as $feature) {
                if ($feature instanceof ControllerSource && $this->isOn($integration, $feature)) {
                    return $feature;
                }
            }
        }

        return null;
    }

    public function configuration(Integration $integration): Configuration
    {
        $stored = $this->stored($integration);

        return new Configuration(
            settings: [...$stored->settings ?? [], ...$this->lockedValues($integration)],
            features: $stored->enabled ? array_values($stored->features ?? []) : [],
            removalLimit: $stored->removal_limit,
        );
    }

    /**
     * The settings set in the environment, which admins can't change, with the variable each
     * comes from.
     *
     * @return array<string, string>
     */
    public function locked(Integration $integration): array
    {
        return collect($integration->settings())
            ->filter(fn (Setting $setting) => array_key_exists($setting->key, $this->lockedValues($integration)))
            ->mapWithKeys(fn (Setting $setting) => [$setting->key => $setting->envVariable])
            ->all();
    }

    /**
     * The settings admins saved, before the environment's values apply. Secrets included.
     *
     * @return array<string, mixed>
     */
    public function saved(Integration $integration): array
    {
        return $this->stored($integration)->settings ?? [];
    }

    /**
     * The features switched on as stored, whether or not the Integration is.
     *
     * @return list<string>
     */
    public function savedFeatures(Integration $integration): array
    {
        return array_values($this->stored($integration)->features ?? []);
    }

    public function removalLimit(Integration $integration): int
    {
        return $this->stored($integration)->removal_limit;
    }

    /**
     * What a save would leave the Integration's configuration as. Locked settings keep their
     * environment value, and a secret left empty keeps the one already saved.
     *
     * @param  array<string, mixed>  $values
     * @param  list<string>  $features
     * @return array{settings: array<string, mixed>, configuration: Configuration}
     */
    public function proposed(Integration $integration, array $values, bool $enabled, array $features, int $removalLimit): array
    {
        $settings = $this->saved($integration);
        $locked = $this->locked($integration);

        foreach ($integration->settings() as $setting) {
            if (array_key_exists($setting->key, $locked) || ! array_key_exists($setting->key, $values)) {
                continue;
            }

            $value = $values[$setting->key];

            if (blank($value)) {
                if (! $setting->secret) {
                    unset($settings[$setting->key]);
                }
            } else {
                $settings[$setting->key] = $value;
            }
        }

        return [
            'settings' => $settings,
            'configuration' => new Configuration(
                settings: [...$settings, ...$this->lockedValues($integration)],
                features: $enabled ? array_values($features) : [],
                removalLimit: $removalLimit,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings  As returned by proposed().
     * @param  list<string>  $features
     */
    public function save(Integration $integration, array $settings, bool $enabled, array $features, int $removalLimit): void
    {
        IntegrationSetting::query()->updateOrCreate(['key' => $integration->key()], [
            'enabled' => $enabled,
            'features' => array_values($features),
            'settings' => $settings,
            'removal_limit' => $removalLimit,
        ]);
    }

    /**
     * Why the features can't all be on together with the ones already on elsewhere, one
     * message per clash. Empty when they can.
     *
     * @param  list<string>  $features
     * @return list<string>
     */
    public function conflicts(Integration $integration, array $features): array
    {
        $proposed = collect($integration->features())->filter(fn (Feature $feature) => in_array($feature->key(), $features, true));

        // Everything that would be on afterwards, as "integration.feature" => [Integration, Feature].
        $on = $this->all()
            ->reject(fn (Integration $other) => $other->key() === $integration->key())
            ->flatMap(fn (Integration $other) => collect($other->features())
                ->filter(fn (Feature $feature) => $this->isOn($other, $feature))
                ->mapWithKeys(fn (Feature $feature) => ["{$other->key()}.{$feature->key()}" => [$other, $feature]]))
            ->merge($proposed->mapWithKeys(fn (Feature $feature) => ["{$integration->key()}.{$feature->key()}" => [$integration, $feature]]));

        $messages = [];

        foreach ($proposed as $feature) {
            $key = "{$integration->key()}.{$feature->key()}";

            foreach ($on as $otherKey => [$otherIntegration, $other]) {
                if ($otherKey === $key) {
                    continue;
                }

                $clash = in_array($otherKey, $feature->conflictsWith(), true)
                    || in_array($key, $other->conflictsWith(), true)
                    || ($feature instanceof ControllerSource && $other instanceof ControllerSource);

                if ($clash) {
                    $messages[] = "{$feature->name()} can’t be on at the same time as {$otherIntegration->name()}’s {$other->name()}.";
                }
            }
        }

        return array_values(array_unique($messages));
    }

    /**
     * Let every feature start listening to the core.
     */
    public function boot(): void
    {
        foreach ($this->all() as $integration) {
            foreach ($integration->features() as $feature) {
                $feature->boot();
            }
        }
    }

    /**
     * Schedule every sync, each running only while its feature is on.
     */
    public function schedule(Schedule $schedule): void
    {
        foreach ($this->all() as $integration) {
            foreach ($integration->features() as $feature) {
                if (! $feature instanceof Syncable) {
                    continue;
                }

                $event = $schedule->job(new RunSync($integration->key(), $feature->key()))
                    ->name("integrations:{$integration->key()}.{$feature->key()}")
                    ->when(fn () => $this->isOn($integration, $feature));

                $feature->schedule($event);
            }
        }
    }

    private function stored(Integration $integration): IntegrationSetting
    {
        return IntegrationSetting::query()->find($integration->key()) ?? new IntegrationSetting(['key' => $integration->key()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function lockedValues(Integration $integration): array
    {
        return array_filter(
            (array) config("integrations.settings.{$integration->key()}", []),
            fn (mixed $value) => filled($value),
        );
    }
}
