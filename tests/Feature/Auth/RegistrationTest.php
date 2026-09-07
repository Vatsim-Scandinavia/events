<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['GET', '/register'])]
    #[TestWith(['POST', '/register'])]
    public function test_local_authentication_endpoint_is_not_available(string $method, string $path): void
    {
        $this->call($method, $path)->assertNotFound();

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }
}
