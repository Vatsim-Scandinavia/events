<?php

namespace Tests\Feature;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    #[TestWith(['127.0.0.1', '127.0.0.1', 'https://events.example/proxy-test'])]
    #[TestWith(['127.0.0.1', '192.0.2.10', 'http://events.example/proxy-test'])]
    #[TestWith(['', '127.0.0.1', 'http://events.example/proxy-test'])]
    public function test_forwarded_https_is_used_only_for_configured_proxies(string $proxies, string $remoteAddress, string $expectedUrl): void
    {
        $previous = $_ENV['TRUSTED_PROXIES'] ?? null;
        $previousServer = $_SERVER['TRUSTED_PROXIES'] ?? null;
        $_ENV['TRUSTED_PROXIES'] = $proxies;
        $_SERVER['TRUSTED_PROXIES'] = $proxies;

        try {
            config(['trustedproxy' => require config_path('trustedproxy.php')]);
        } finally {
            if ($previous === null) {
                unset($_ENV['TRUSTED_PROXIES']);
            } else {
                $_ENV['TRUSTED_PROXIES'] = $previous;
            }

            if ($previousServer === null) {
                unset($_SERVER['TRUSTED_PROXIES']);
            } else {
                $_SERVER['TRUSTED_PROXIES'] = $previousServer;
            }
        }

        Route::get('/proxy-test', fn (): array => ['url' => url('/proxy-test')]);

        $this->withServerVariables(['REMOTE_ADDR' => $remoteAddress])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'])
            ->get('http://events.example/proxy-test')
            ->assertExactJson(['url' => $expectedUrl]);
    }

    #[TestWith(['127.0.0.1', 'https://events-8080.app.github.dev/firs'])]
    #[TestWith(['192.0.2.10', 'http://localhost:8080/firs'])]
    public function test_redirects_use_the_forwarded_origin_only_from_a_trusted_gateway(string $remoteAddress, string $expectedUrl): void
    {
        config(['trustedproxy.proxies' => '127.0.0.1']);
        Route::post('/proxy-test', fn (): RedirectResponse => to_route('firs.index'));

        $this->withServerVariables(['REMOTE_ADDR' => $remoteAddress])
            ->withHeaders([
                'X-Forwarded-Host' => 'events-8080.app.github.dev',
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Port' => '443',
            ])
            ->post('http://localhost:8080/proxy-test')
            ->assertRedirect($expectedUrl);
    }
}
