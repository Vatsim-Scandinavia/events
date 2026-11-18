<?php

namespace App\Enums;

/**
 * What the daily sync did with one Account (see App\Auth\MemberDetailsSync).
 */
enum MemberSyncOutcome: string
{
    /** Full Member Details, read with the Account's own tokens. */
    case ViaToken = 'via_token';

    /** Rating and subdivision only, from VATSIM's public member record. */
    case ViaPublicRecord = 'via_public_record';

    /** Nothing could be read, so the Account was left as it was. */
    case Skipped = 'skipped';

    /** Found suspended or inactive, and signed out everywhere. */
    case Suspended = 'suspended';
}
