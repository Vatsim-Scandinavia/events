<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['GET', '/settings/security'])]
    #[TestWith(['PUT', '/settings/password'])]
    #[TestWith(['POST', '/user/two-factor-authentication'])]
    #[TestWith(['POST', '/user/passkeys'])]
    #[TestWith(['GET', '/.well-known/passkey-endpoints'])]
    public function test_local_security_endpoints_are_not_available(string $method, string $path): void
    {
        $user = User::factory()->create()->refresh();

        $this->actingAs($user)->call($method, $path)->assertNotFound();

        $this->assertSame($user->getAttributes(), $user->fresh()->getAttributes());
    }
}
