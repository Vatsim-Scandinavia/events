<?php

namespace Tests\Feature;

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
}
