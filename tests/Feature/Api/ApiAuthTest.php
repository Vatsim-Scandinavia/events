<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    private function makeKey(bool $readOnly = false): string
    {
        $plain = bin2hex(random_bytes(32));
        ApiKey::create([
            'name'      => 'test-key',
            'key'       => hash('sha256', $plain),
            'read_only' => $readOnly,
        ]);

        return $plain;
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/events');

        $response->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized']);
    }

    public function test_invalid_token_returns_401(): void
    {
        $response = $this->withToken('totally-invalid-token')->getJson('/api/events');

        $response->assertStatus(401);
    }

    public function test_valid_token_allows_get_request(): void
    {
        $plain = $this->makeKey();

        $response = $this->withToken($plain)->getJson('/api/events');

        $response->assertSuccessful();
    }

    public function test_read_only_key_blocks_post_request(): void
    {
        $plain = $this->makeKey(readOnly: true);

        $response = $this->withToken($plain)->postJson('/api/staffings/book', []);

        $response->assertStatus(403)
            ->assertJson(['error' => 'This API key is read-only']);
    }

    public function test_read_only_key_allows_get_request(): void
    {
        $plain = $this->makeKey(readOnly: true);

        $response = $this->withToken($plain)->getJson('/api/events');

        $response->assertSuccessful();
    }

    public function test_successful_request_updates_last_used_at(): void
    {
        $plain = $this->makeKey();

        $this->withToken($plain)->getJson('/api/events');

        $this->assertDatabaseMissing('api_keys', ['last_used_at' => null]);
    }
}
