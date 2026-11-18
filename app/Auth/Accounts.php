<?php

namespace App\Auth;

use App\Models\User;
use InvalidArgumentException;

/**
 * The Hub's Accounts: its record of each Member, by CID.
 */
final class Accounts
{
    /**
     * Write the Member Details onto their Account, creating it on first sign-in. Full details
     * replace name, first name and email (a missing name becomes "VATSIM {CID}"); partial ones
     * only touch rating and subdivision, and only ever update an Account that exists.
     *
     * Nothing else needs re-checking: the Controller Rule is worked out live from these details.
     *
     * @throws InvalidArgumentException when partial details name an Account that doesn't exist
     */
    public function apply(MemberDetails $details): User
    {
        if ($details->isPartial) {
            $user = User::query()->where('vatsim_cid', $details->cid)->first()
                ?? throw new InvalidArgumentException("There's no Account for CID {$details->cid} to update.");
        } else {
            $user = User::query()->firstOrNew(['vatsim_cid' => $details->cid]);
            $user->fill([
                'name' => $details->name ?? "VATSIM {$details->cid}",
                'first_name' => $details->firstName,
                'email' => $details->email,
            ]);
        }

        if ($details->sharedVatsimDetails) {
            // A rating the Hub doesn't know leaves the one it has.
            if ($details->rating !== null) {
                $user->rating = $details->rating;
            }

            $user->subdivision = $details->subdivision;
        }

        $user->save();

        return $user;
    }
}
