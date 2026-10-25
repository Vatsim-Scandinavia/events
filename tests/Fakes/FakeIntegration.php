<?php

namespace Tests\Fakes;

use App\Integrations\Configuration;
use App\Integrations\ConnectionFailed;
use App\Integrations\Integration;
use App\Integrations\Setting;

/**
 * A stand-in external system for testing the Integration framework. Its connection test
 * fails for the URL https://down.example.
 */
class FakeIntegration extends Integration
{
    public const string DOWN_URL = 'https://down.example';

    public function key(): string
    {
        return 'fake';
    }

    public function name(): string
    {
        return 'Fake System';
    }

    public function description(): string
    {
        return 'A training system that only exists in tests.';
    }

    public function settings(): array
    {
        return [
            new Setting('url', 'URL', 'FAKE_URL', ['url:https']),
            new Setting('token', 'API token', 'FAKE_TOKEN', secret: true),
        ];
    }

    public function features(): array
    {
        return [new FakeRosterSync, new FakeBookingPush];
    }

    public function testConnection(Configuration $configuration): void
    {
        if ($configuration->get('url') === self::DOWN_URL) {
            throw new ConnectionFailed('The fake system did not answer.');
        }
    }
}
