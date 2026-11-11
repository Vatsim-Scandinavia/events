<?php

namespace App\Enums;

/**
 * How a user's name is shown to everyone else on the Hub: next to their CID on rosters and
 * bookings, in Discord roster messages and in the public API.
 */
enum NameDisplay: string
{
    case FullName = 'full_name';
    case FirstName = 'first_name';
    case CidOnly = 'cid_only';

    public function label(): string
    {
        return match ($this) {
            self::FullName => 'Full name',
            self::FirstName => 'First name',
            self::CidOnly => 'CID only',
        };
    }
}
