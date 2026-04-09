<?php

namespace Tests\Unit;

use App\Clients\DiscordClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscordClientTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        config([
            'services.discord.webhook_url' => 'https://discord.com/api/webhooks/test-webhook-url',
            'services.discord.bot_api_url' => 'https://discord.com/api/v10',
            'services.discord.bot_api_token' => 'test-bot-api-token',
            'services.discord.bot_token' => 'test-bot-token',
            'services.discord.guild_id' => 'test-guild-id',
        ]);
    }

    public function test_sendWebhook_succeeds_with_valid_payload()
    {
        Http::fake([
            'https://discord.com/api/webhooks/test-webhook-url' => Http::response(['id' => '123'], 200),
        ]);

        $client = new DiscordClient();
        $payload = ['content' => 'Test message'];

        $result = $client->sendWebhook($payload);

        $this->assertTrue($result);
        Http::assertSent(function ($request) use ($payload) {
            return $request->url() === 'https://discord.com/api/webhooks/test-webhook-url' &&
                $request['content'] === 'Test message';
        });
    }

    public function test_sendWebhook_fails_without_webhook_url()
    {
        config(['services.discord.webhook_url' => null]);

        $client = new DiscordClient();
        $payload = ['content' => 'Test'];

        $result = $client->sendWebhook($payload);

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    public function test_sendWebhook_fails_on_http_error()
    {
        Http::fake([
            'https://discord.com/api/webhooks/test-webhook-url' => Http::response(['error' => 'Bad Request'], 400),
        ]);

        $client = new DiscordClient();
        $payload = ['content' => 'Test'];

        $result = $client->sendWebhook($payload);

        $this->assertFalse($result);
    }

    public function test_sendBot_succeeds_with_valid_payload()
    {
        Http::fake([
            '*/api/v10*' => Http::response(['id' => '456'], 200),
        ]);

        $client = new DiscordClient();
        $payload = ['content' => 'Bot message'];
        $path = '/test-endpoint';


        $result = $client->sendBot($payload, $path);

        $this->assertTrue($result);
    }

    public function test_sendBot_fails_without_api_url_or_token()
    {
        config(['services.discord.bot_api_url' => null]);

        $client = new DiscordClient();
        $payload = ['content' => 'Test'];
        $path = '/test-endpoint';

        $result = $client->sendBot($payload, $path);

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    public function test_get_succeeds_with_valid_endpoint()
    {
        Http::fake([
            '*/api/v10*' => Http::response([
                ['id' => '123', 'name' => 'general']
            ], 200),
        ]);

        $client = new DiscordClient();
        $endpoint = '/guilds/test-guild-id/channels';

        $result = $client->get($endpoint);

        $this->assertIsArray($result);
        $this->assertEquals('general', $result[0]['name']);
    }

    public function test_get_returns_null_without_bot_token()
    {
        config(['services.discord.bot_token' => null]);

        $client = new DiscordClient();

        $result = $client->get('/test');

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    public function test_get_returns_null_on_http_error()
    {
        Http::fake([
            'https://discord.com/api/v10/test' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $client = new DiscordClient();

        $result = $client->get('/test');

        $this->assertNull($result);
    }
}
