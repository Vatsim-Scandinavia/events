<?php

namespace App\Integrations\ControlCenter;

use App\Models\Fir;

/**
 * Which of the Hub's FIRs each Control Center area is, as admins write it: one "area = ICAO"
 * per line, or separated by commas when set in the environment. Control Center names areas
 * rather than coding them, so they're matched without regard to case.
 */
final readonly class AreaMap
{
    /**
     * @param  array<string, string>  $firs  Lowercased area name => FIR ICAO code.
     */
    private function __construct(public array $firs) {}

    public static function parse(?string $text): self
    {
        $firs = [];

        foreach (self::entries($text) as $entry) {
            if ($pair = self::pair($entry)) {
                $firs[$pair[0]] = $pair[1];
            }
        }

        return new self($firs);
    }

    /**
     * What's wrong with the text, for admins to fix. Empty when nothing is.
     *
     * @return list<string>
     */
    public static function problems(string $text): array
    {
        $known = Fir::query()->pluck('icao')->all();
        $problems = [];

        foreach (self::entries($text) as $entry) {
            $pair = self::pair($entry);

            if ($pair === null) {
                $problems[] = "Write “{$entry}” as an area and a FIR, like “denmark = EKDK”.";
            } elseif (! in_array($pair[1], $known, true)) {
                $problems[] = "{$pair[1]} isn’t one of the Hub’s FIRs.";
            }
        }

        return $problems;
    }

    public function fir(string $area): ?string
    {
        return $this->firs[mb_strtolower(trim($area))] ?? null;
    }

    /**
     * @return list<string>
     */
    public function areas(): array
    {
        return array_keys($this->firs);
    }

    /**
     * @return list<string>
     */
    private static function entries(?string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $text) ?: [])));
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private static function pair(string $entry): ?array
    {
        if (! preg_match('/^(.+?)\s*[=:]\s*([A-Za-z]{4})$/u', $entry, $match)) {
            return null;
        }

        return [mb_strtolower(trim($match[1])), strtoupper($match[2])];
    }
}
