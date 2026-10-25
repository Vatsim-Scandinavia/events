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

    /**
     * The CSS variables each Brand color sets. Each also sets its "-foreground" variable.
     *
     * @var array<string, list<string>>
     */
    private const array BRAND_VARIABLES = [
        'primary' => ['primary', 'sidebar-primary'],
        'accent' => ['accent', 'secondary', 'sidebar-accent'],
    ];

    /**
     * The text colors put on a Brand color, matching the Default theme's lightest and darkest.
     */
    private const string LIGHT_TEXT = '#ffffff';

    private const string DARK_TEXT = '#0a0a0a';

    private const string CACHE_KEY = 'site-settings';

    /**
     * Forgotten whenever a FIR is saved or deleted.
     */
    public const string FIRS_CACHE_KEY = 'vacc-firs';

    /**
     * The details as the site shows them. FIRs are the vACC's FIR records, by ICAO code.
     *
     * @return array{name: string, division: ?string, subdivision: ?string, firs: list<array{icao: string, name: string}>, website: ?string, logo: ?string, heroGradient: array{from: string, to: string}|null}
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
            'heroGradient' => $stored['hero_gradient'] ?? null,
        ];
    }

    /**
     * The colors of the gradient behind the front page's hero, or null for the default look.
     *
     * @param  array{from: string, to: string}|null  $gradient
     */
    public function saveHeroGradient(?array $gradient): void
    {
        $this->put('hero_gradient', $gradient);
    }

    public function theme(): Theme
    {
        return Theme::tryFrom($this->stored()['theme'] ?? '') ?? Theme::Default;
    }

    /**
     * The Brand theme's colors. They're kept while the Default theme is in use.
     *
     * @return array{primary: string, accent: string}|null
     */
    public function brandColors(): ?array
    {
        return $this->stored()['brand_colors'] ?? null;
    }

    /**
     * @param  array{primary: string, accent: string}|null  $colors
     */
    public function saveTheme(Theme $theme, ?array $colors): void
    {
        $this->put('theme', $theme === Theme::Default ? null : $theme->value);
        $this->put('brand_colors', $colors);
    }

    /**
     * The CSS variables the theme overrides on every page, each with readable text to go on it.
     * Empty for the Default theme.
     *
     * @return array<string, string>
     */
    public function themeVariables(): array
    {
        $colors = $this->brandColors();

        if ($this->theme() !== Theme::Brand || $colors === null) {
            return [];
        }

        $variables = [];

        foreach (self::BRAND_VARIABLES as $color => $names) {
            $text = $this->readableTextOn($colors[$color]);

            foreach ($names as $name) {
                $variables["--{$name}"] = $colors[$color];
                $variables["--{$name}-foreground"] = $text;
            }
        }

        $variables['--ring'] = $colors['primary'];

        return $variables;
    }

    /**
     * White or near-black, whichever contrasts better with the color (WCAG relative luminance).
     */
    private function readableTextOn(string $hex): string
    {
        $luminance = $this->luminance($hex);

        $againstLight = ($this->luminance(self::LIGHT_TEXT) + 0.05) / ($luminance + 0.05);
        $againstDark = ($luminance + 0.05) / ($this->luminance(self::DARK_TEXT) + 0.05);

        return $againstDark > $againstLight ? self::DARK_TEXT : self::LIGHT_TEXT;
    }

    private function luminance(string $hex): float
    {
        [$red, $green, $blue] = array_map(function (string $pair): float {
            $value = hexdec($pair) / 255;

            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
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
