<?php

namespace Tests\Unit;

use App\Auth\VatsimProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class VatsimProviderTest extends TestCase
{
    public function test_connect_and_handover_user_responses_are_mapped_by_cid(): void
    {
        foreach (['https://auth.vatsim.net', 'https://handover.example'] as $baseUrl) {
            $history = [];
            $handler = HandlerStack::create(new MockHandler([
                new Response(200, [], json_encode(['data' => [
                    'cid' => '1234567',
                    'personal' => ['name_full' => 'Pilot Name', 'email' => 'pilot@example.com'],
                ]], JSON_THROW_ON_ERROR)),
            ]));
            $handler->push(Middleware::history($history));
            $provider = (new VatsimProvider(Request::create('/'), 'client', 'secret', 'http://localhost/auth/callback'))
                ->setBaseUrl($baseUrl)->setHttpClient(new Client(['handler' => $handler]));
            $user = $provider->userFromToken('token');
            $this->assertSame('1234567', $user->getId());
            $this->assertSame('Pilot Name', $user->getName());
            $this->assertSame('pilot@example.com', $user->getEmail());
            $this->assertSame($baseUrl.'/api/user', (string) $history[0]['request']->getUri());
            $this->assertSame('Bearer token', $history[0]['request']->getHeaderLine('Authorization'));
        }
    }
}
