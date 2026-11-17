<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;

abstract class TestCase extends BaseTestCase
{
    /**
     * Trusted hosts are kept on the request class itself, and set whenever a test runs the app
     * as a server (e.g. APP_ENV=staging), so clear them before the next test.
     */
    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);

        parent::tearDown();
    }
}
