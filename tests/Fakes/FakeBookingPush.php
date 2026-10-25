<?php

namespace Tests\Fakes;

use App\Integrations\Feature;

/**
 * A feature with nothing to sync, that clashes with the other fake system's booking push.
 */
class FakeBookingPush extends Feature
{
    public function key(): string
    {
        return 'bookings';
    }

    public function name(): string
    {
        return 'Booking push';
    }

    public function description(): string
    {
        return 'Sends bookings to the fake system.';
    }

    public function conflictsWith(): array
    {
        return ['other.bookings'];
    }
}
