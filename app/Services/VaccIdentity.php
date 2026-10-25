<?php

namespace App\Services;

use App\Enums\Theme;
use App\Models\Fir;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Who the installation belongs to: the vACC's name, division, FIRs, website and logo.
 * FIRs are records of their own, set up on the FIRs admin page.
 *
 * Each detail comes from the server's environment when it is set there, which locks it.
 * Otherwise it is whatever an admin saved on the settings page, and failing that a default.
 */
final class VaccIdentity
{
    public const string DEFAULT_NAME = 'Example vACC';

    /**
     * The details admins can change, and the environment variable that locks each one.
     *
     * @var array<string, string>
     */
    public const array FIELDS = [
        'name' => 'VACC_NAME',
        'division' => 'VACC_DIVISION',
        'subdivision' => 'VACC_SUBDIVISION',
        'website' => 'VACC_WEBSITE',
    ];

    private const string CACHE_KEY = 'site-settings';

    /**
     * Forgotten whenever a FIR is saved or deleted.
     */
    public const string FIRS_CACHE_KEY = 'vacc-firs';

    /**
     * The details as the site shows them. FIRs are the vACC's FIR records, by ICAO code.
     *
     * @return array{name: string, division: ?string, subdivision: ?string, firs: list<array{icao: string, name: string}>, website: ?string, logo: ?string}
     */
    public function effective(): array
    {
        $stored = $this->stored();
        $value = fn (string $field) => config("vacc.{$field}") ?? $stored[$field] ?? null;

        return [
            'name' => $value('name') ?? self::DEFAULT_NAME,
            'division' => $value('division'),
            'subdivision' => $value('subdivision'),
            'firs' => Cache::rememberForever(self::FIRS_CACHE_KEY, fn () => Fir::query()->orderBy('icao')->get(['icao', 'name'])->toArray()),
            'website' => $value('website'),
            'logo' => $this->url($this->logo()['logo'] ?? null),
        ];
    }

    /**
     * The colors the whole Hub is shown in. Each theme's colors live in resources/css/app.css.
     */
    public function theme(): Theme
    {
        return Theme::tryFrom($this->stored()['theme'] ?? '') ?? Theme::Default;
    }

    public function saveTheme(Theme $theme): void
    {
        $this->put('theme', $theme === Theme::Default ? null : $theme->value);
    }

    /**
     * The details set in the environment, which admins can't change.
     *
     * @return list<string>
     */
    public function locked(): array
    {
        return array_values(array_filter(array_keys(self::FIELDS), fn (string $field) => config("vacc.{$field}") !== null));
    }

    /**
     * Save details chosen on the settings page. Locked details are left alone, and empty
     * ones are forgotten so the default applies again.
     *
     * @param  array<string, mixed>  $values
     */
    public function save(array $values): void
    {
        foreach (array_diff(array_keys(self::FIELDS), $this->locked()) as $field) {
            if (array_key_exists($field, $values)) {
                $this->put($field, $values[$field]);
            }
        }
    }

    /**
     * Whether the hub hands out the Controller role itself (see App\Auth\ControllerRole).
     */
    public function controllerRoleIsAutomatic(): bool
    {
        return (bool) ($this->stored()['auto_controller'] ?? false);
    }

    public function setControllerRoleAutomatic(bool $automatic): void
    {
        $this->put('auto_controller', $automatic ?: null);
    }

    /**
     * The stored logo files, as paths on the public disk.
     *
     * @return array{logo: string, icon: string, touch: ?string, type: string}|null
     */
    public function logo(): ?array
    {
        return $this->stored()['logo'] ?? null;
    }

    /**
     * @param  array{logo: string, icon: string, touch: ?string, type: string}|null  $logo
     */
    public function saveLogo(?array $logo): void
    {
        $this->put('logo', $logo);
    }

    /**
     * The favicon links for the page head: the logo's icons, or the bundled favicon.
     *
     * @return array{icon: string, type: string, touch: ?string}
     */
    public function favicon(): array
    {
        $logo = $this->logo();

        if ($logo === null) {
            return ['icon' => asset('favicon.ico'), 'type' => 'image/x-icon', 'touch' => null];
        }

        return ['icon' => $this->url($logo['icon']), 'type' => $logo['type'], 'touch' => $this->url($logo['touch'])];
    }

    private function put(string $key, mixed $value): void
    {
        if ($value === null || $value === '' || $value === []) {
            SiteSetting::query()->whereKey($key)->delete();
        } else {
            SiteSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => SiteSetting::query()->pluck('value', 'key')->all());
    }

    private function url(?string $path): ?string
    {
        return $path !== null ? Storage::disk('public')->url($path) : null;
    }
}
