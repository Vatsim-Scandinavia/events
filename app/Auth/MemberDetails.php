<?php

namespace App\Auth;

use App\Enums\ControllerRating;
use Illuminate\Support\Arr;

/**
 * What VATSIM says about a Member that the Hub keeps on their Account: name, first name, email,
 * rating and subdivision. Every source maps into this, so the rules for applying it live in one
 * place (Accounts::apply).
 *
 * Partial details come from VATSIM's public member API, which only knows rating and subdivision;
 * applying them leaves name, first name and email as they are. A null subdivision always means
 * "not in one", never "unknown". A null rating means one the Hub doesn't know, which is ignored.
 */
final readonly class MemberDetails
{
    private function __construct(
        public int $cid,
        public ?string $name,
        public ?string $firstName,
        public ?string $email,
        public ?ControllerRating $rating,
        public ?string $subdivision,
        public bool $sharedVatsimDetails,
        public bool $isPartial,
    ) {}

    /**
     * From the `data` of a Sign-in Provider's /api/user. Null when it holds no CID. A member who
     * didn't share the vatsim_details scope has no `vatsim` section.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromSignIn(array $data): ?self
    {
        $cid = Arr::get($data, 'cid');

        if (! is_numeric($cid) || (int) $cid <= 0) {
            return null;
        }

        $vatsim = Arr::get($data, 'vatsim');
        $shared = is_array($vatsim) && Arr::has($vatsim, 'rating.id');

        return new self(
            cid: (int) $cid,
            name: self::text(Arr::get($data, 'personal.name_full')),
            firstName: self::text(Arr::get($data, 'personal.name_first')),
            email: self::text(Arr::get($data, 'personal.email')),
            rating: $shared ? ControllerRating::tryFrom((int) Arr::get($vatsim, 'rating.id')) : null,
            subdivision: $shared ? self::text(Arr::get($vatsim, 'subdivision.id')) : null,
            sharedVatsimDetails: $shared,
            isPartial: false,
        );
    }

    /**
     * From VATSIM's public member record (api.vatsim.net/v2/members/{cid}), which carries a rating
     * and a subdivision_id. Null when the record has no rating to go on.
     *
     * @param  array<string, mixed>  $member
     */
    public static function fromPublicRecord(int $cid, array $member): ?self
    {
        if (! array_key_exists('rating', $member)) {
            return null;
        }

        return new self(
            cid: $cid,
            name: null,
            firstName: null,
            email: null,
            rating: ControllerRating::tryFrom((int) $member['rating']),
            subdivision: self::text($member['subdivision_id'] ?? null),
            sharedVatsimDetails: true,
            isPartial: true,
        );
    }

    /**
     * Suspended or inactive on the network, so sign-in turns them away.
     */
    public function isSuspended(): bool
    {
        return in_array($this->rating, [ControllerRating::Suspended, ControllerRating::Inactive], true);
    }

    private static function text(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }
}
