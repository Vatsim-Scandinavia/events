<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Vite;
use Inertia\Ssr\HttpGateway;
use Tests\TestCase;

class DevContainerSsrTest extends TestCase
{
    public function test_ssr_uses_the_internal_server_instead_of_the_authenticated_codespaces_url(): void
    {
        $previous = $_ENV['INERTIA_SSR_HOT_URL'] ?? null;
        $previousServer = $_SERVER['INERTIA_SSR_HOT_URL'] ?? null;
        $_ENV['INERTIA_SSR_HOT_URL'] = 'http://127.0.0.1:5173';
        $_SERVER['INERTIA_SSR_HOT_URL'] = 'http://127.0.0.1:5173';

        try {
            config(['inertia' => require config_path('inertia.php')]);
        } finally {
            if ($previous === null) {
                unset($_ENV['INERTIA_SSR_HOT_URL']);
            } else {
                $_ENV['INERTIA_SSR_HOT_URL'] = $previous;
            }

            if ($previousServer === null) {
                unset($_SERVER['INERTIA_SSR_HOT_URL']);
            } else {
                $_SERVER['INERTIA_SSR_HOT_URL'] = $previousServer;
            }
        }

        Vite::shouldReceive('isRunningHot')->once()->andReturn(true);
        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:5173/__inertia_ssr' => Http::response([
                'head' => [],
                'body' => '<main>Server rendered content</main>',
            ]),
        ]);

        $response = app(HttpGateway::class)->dispatch(['component' => 'welcome', 'props' => [], 'url' => '/']);

        $this->assertSame('<main>Server rendered content</main>', $response?->body);
    }
}
