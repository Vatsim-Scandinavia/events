<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_only_run_scripts_carrying_the_nonce_and_cannot_be_framed(): void
    {
        $response = $this->get('/events')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('Strict-Transport-Security');

        $policy = (string) $response->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression("/script-src ([^;]*)'nonce-([^']+)'/", $policy);
        preg_match('/script-src ([^;]*)/', $policy, $scriptSources);
        preg_match("/'nonce-([^']+)'/", $policy, $nonce);

        $this->assertStringNotContainsString("'unsafe-inline'", $scriptSources[1]);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringContainsString("object-src 'none'", $policy);
        $response->assertSee('nonce="'.$nonce[1].'"', false);
    }

    public function test_https_requests_are_told_to_stay_on_https(): void
    {
        $this->get('https://localhost/events')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
