<?php

namespace App\Auth;

use App\Models\Fir;

/**
 * Where something must be held for a check to pass. Whether a role applies Hub-wide or in one
 * FIR is decided by each role assignment (no FIR means Hub-wide), never by the role itself.
 *
 * - hub(): held Hub-wide.
 * - in($fir): held Hub-wide, or in that FIR.
 * - anywhere(): held Hub-wide, or in at least one FIR.
 */
final readonly class Where
{
    private function __construct(public ?Fir $fir, private bool $anywhere) {}

    public static function hub(): self
    {
        return new self(null, false);
    }

    public static function in(Fir $fir): self
    {
        return new self($fir, false);
    }

    public static function anywhere(): self
    {
        return new self(null, true);
    }

    /**
     * One place a role can be given: in the FIR, or Hub-wide when there is none.
     */
    public static function at(?Fir $fir): self
    {
        return $fir !== null ? self::in($fir) : self::hub();
    }

    /**
     * Whether a role assignment held in the given FIR (null for Hub-wide) counts here.
     */
    public function covers(int|string|null $assignmentFirId): bool
    {
        if ($assignmentFirId === null || $this->anywhere) {
            return true;
        }

        return $this->fir !== null && (int) $assignmentFirId === $this->fir->id;
    }
}
