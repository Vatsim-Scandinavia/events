<?php

namespace App\Integrations\ControlCenter;

use RuntimeException;

/**
 * Control Center turned a booking down, saying why. Sending it again unchanged won't help,
 * unless it's retryable: VATSIM's booking service failed behind Control Center.
 */
final class BookingRefused extends RuntimeException
{
    public function __construct(string $reason, public readonly bool $retryable = false)
    {
        parent::__construct($reason);
    }
}
