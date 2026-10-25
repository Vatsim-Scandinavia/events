<?php

namespace App\Integrations\ControlCenter;

use App\Enums\ControllerRating;

/**
 * Whether Control Center's roster says a controller may work a position in a FIR, as precisely
 * as its API allows. A solo endorsement covers its positions. Otherwise the controller must be
 * active in an area mapped to the FIR, or hold a visiting endorsement for one, with the rating
 * the position needs: DEL and GND S1, TWR S2, APP and DEP S3, CTR and FSS C1.
 *
 * Facility endorsements (major airports) are ignored: the API doesn't say which positions need
 * one. Anything it can't tell, such as a position with another suffix, is no warning.
 */
final readonly class Coverage
{
    /** @var array<string, ControllerRating> */
    private const array REQUIRED = [
        'DEL' => ControllerRating::S1,
        'GND' => ControllerRating::S1,
        'TWR' => ControllerRating::S2,
        'APP' => ControllerRating::S3,
        'DEP' => ControllerRating::S3,
        'CTR' => ControllerRating::C1,
        'FSS' => ControllerRating::C1,
    ];

    public function __construct(private AreaMap $areas) {}

    /**
     * Why the member isn't covered for the callsign in the FIR, or null when they are, or it
     * can't tell. A controller Control Center doesn't list at all is someone staff gave the role
     * by hand, so there's nothing to say.
     */
    public function problem(?Member $member, string $callsign, string $firIcao): ?string
    {
        $required = self::REQUIRED[strtoupper((string) str($callsign)->afterLast('_'))] ?? null;

        if ($member === null || $required === null || $this->hasSolo($member, $callsign)) {
            return null;
        }

        $firAreas = array_keys(array_filter($this->areas->firs, fn (string $icao) => $icao === $firIcao));

        if (array_intersect($member->active_areas, $firAreas) !== []) {
            $rating = $this->rating($member->rating);

            return $rating !== null && $rating->value < $required->value
                ? "Rated {$rating->label()} in Control Center; {$callsign} needs {$required->label()}."
                : null;
        }

        $visiting = collect($member->endorsements['visiting'] ?? [])
            ->filter(fn (mixed $endorsement) => is_array($endorsement)
                && array_intersect(array_map(fn (mixed $area) => mb_strtolower((string) $area), (array) ($endorsement['areas'] ?? [])), $firAreas) !== [])
            ->map(fn (array $endorsement) => $this->rating($endorsement['rating'] ?? null))
            ->filter()
            ->sortByDesc(fn (ControllerRating $rating) => $rating->value)
            ->first();

        if ($visiting !== null) {
            return $visiting->value < $required->value
                ? "Visiting endorsement for {$visiting->label()} only; {$callsign} needs {$required->label()}."
                : null;
        }

        return "Not active in {$firIcao} in Control Center, and no visiting endorsement for it.";
    }

    private function hasSolo(Member $member, string $callsign): bool
    {
        return collect($member->endorsements['solo'] ?? [])->contains(fn (mixed $endorsement) => is_array($endorsement)
            && in_array(strtoupper($callsign), array_map(fn (mixed $position) => strtoupper((string) $position), (array) ($endorsement['positions'] ?? [])), true));
    }

    private function rating(mixed $label): ?ControllerRating
    {
        return collect(ControllerRating::cases())->first(fn (ControllerRating $rating) => $rating->label() === $label);
    }
}
