<?php

namespace Tests;

use App\Auth\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;

abstract class TestCase extends BaseTestCase
{
    /**
     * The built-in roles come from `php artisan roles:sync` rather than a migration, so
     * every test with a database starts out with them, as a deployed Hub does.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (in_array(RefreshDatabase::class, class_uses_recursive($this), true)) {
            Roles::sync();
        }
    }

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
