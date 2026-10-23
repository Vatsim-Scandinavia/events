<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class BehindProxyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A request that reached the app over plain HTTP from a proxy that says the visitor used HTTPS.
     */
    private function throughProxy(string $uri)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'])
            ->get($uri);
    }

    public function test_https_is_detected_behind_a_trusted_proxy(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.2']);

        $this->throughProxy('/')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $this->assertTrue(request()->isSecure());
        $this->assertStringStartsWith('https://', url('/events'));
    }

    public function test_forwarded_headers_from_untrusted_addresses_are_ignored(): void
    {
        config(['trustedproxy.proxies' => null]);

        $this->throughProxy('/')->assertHeaderMissing('Strict-Transport-Security');
        $this->assertFalse(request()->isSecure());
    }

    public function test_a_plain_error_page_shows_when_the_frontend_cannot_render(): void
    {
        config(['app.debug' => false]);
        Vite::useManifestFilename('missing-manifest.json');

        $this->get('/events')
            ->assertStatus(500)
            ->assertSee('Something went wrong')
            ->assertDontSee('missing-manifest.json');
    }
}
