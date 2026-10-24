<?php

namespace Tests\Fakes;

use App\Integrations\Configuration;
use App\Integrations\Feature;
use App\Integrations\Integration;

/**
 * A second stand-in system, whose features clash with FakeIntegration's.
 */
class OtherIntegration extends Integration
{
    public function key(): string
    {
        return 'other';
    }

    public function name(): string
    {
        return 'Other System';
    }

    public function description(): string
    {
        return 'Another system that only exists in tests.';
    }

    public function settings(): array
    {
        return [];
    }

    public function features(): array
    {
        return [
            new class extends FakeRosterSync
            {
                public function controllerSourceName(): string
                {
                    return 'Other System roster sync';
                }
            },
            new class extends Feature
            {
                public function key(): string
                {
                    return 'bookings';
                }

                public function name(): string
                {
                    return 'Network bookings';
                }

                public function description(): string
                {
                    return 'Sends bookings straight to the network.';
                }
            },
        ];
    }

    public function testConnection(Configuration $configuration): void {}
}
