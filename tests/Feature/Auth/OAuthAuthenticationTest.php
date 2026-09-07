<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\OAuthController;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Facades\Socialite;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class OAuthAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private array $history = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['vatsim', 'handover'] as $provider) {
            config([
                'services.'.$provider.'.enabled' => true,
                'services.'.$provider.'.client_id' => 'test-client',
                'services.'.$provider.'.client_secret' => 'test-secret',
                'services.'.$provider.'.redirect' => '/auth/'.$provider.'/callback',
            ]);
        }

        config([
            'services.vatsim.base_url' => 'https://auth.vatsim.net',
            'services.handover.base_url' => 'https://handover.vatsim-scandinavia.org',
        ]);
    }

    public function test_login_lists_configured_providers_with_vatsim_primary(): void
    {
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->where('providers', [
                ['id' => 'vatsim', 'name' => 'VATSIM Connect', 'primary' => true],
                ['id' => 'handover', 'name' => 'Handover', 'primary' => false],
            ])
            ->missing('client_secret'));
    }

    public function test_login_hides_disabled_and_unconfigured_providers(): void
    {
        config(['services.handover.enabled' => false, 'services.vatsim.client_secret' => null]);

        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')->where('providers', []));
    }

    #[TestWith(['vatsim', 'auth.vatsim.net'])]
    #[TestWith(['handover', 'handover.vatsim-scandinavia.org'])]
    public function test_redirect_requests_profile_scopes_and_stores_state(string $provider, string $host): void
    {
        $response = $this->get(route('oauth.redirect', $provider));

        $url = $response->headers->get('Location');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame($host, parse_url($url, PHP_URL_HOST));
        $this->assertSame('/oauth/authorize', parse_url($url, PHP_URL_PATH));
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('full_name email vatsim_details', $query['scope']);
        $this->assertSame(url('/auth/'.$provider.'/callback'), $query['redirect_uri']);
        $this->assertNotEmpty($query['state']);
        $response->assertSessionHas('state', $query['state'])->assertSessionHas('oauth.provider', $provider);
    }

    #[TestWith(['vatsim', 'https://auth.vatsim.net'])]
    #[TestWith(['handover', 'https://handover.vatsim-scandinavia.org'])]
    public function test_callback_creates_cid_account_and_logs_in(string $provider, string $baseUrl): void
    {
        $this->freezeTime();
        $this->fakeProvider($provider, $this->profile());
        $this->startSession();
        $oldSessionId = session()->getId();

        $response = $this->withSession(['state' => 'test-state', 'oauth.provider' => $provider])
            ->get(route('oauth.callback', ['provider' => $provider, 'state' => 'test-state', 'code' => 'test-code']));

        $response->assertRedirect(route('dashboard'))->assertSessionMissing('state')->assertSessionMissing('oauth.provider');
        $user = User::query()->findOrFail(1234567);
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertSame(1234567, $user->getAuthIdentifier());
        $this->assertFalse($user->getIncrementing());
        $this->assertDatabaseHas('users', [
            'cid' => 1234567, 'name_full' => 'Test Controller', 'email' => 'controller@example.com',
            'controller_rating' => 5, 'division' => 'EUD', 'subdivision' => 'SCA',
            'oauth_provider' => $provider, 'oauth_id' => '1234567',
        ]);
        $this->assertSame('access-token', $user->oauth_access_token);
        $this->assertSame('refresh-token', $user->oauth_refresh_token);
        $this->assertSame(now()->addHour()->getTimestamp(), $user->oauth_expires_at->getTimestamp());
        $stored = DB::table('users')->where('cid', 1234567)->first();
        $this->assertNotSame('access-token', $stored->oauth_access_token);
        $this->assertSame('refresh-token', Crypt::decryptString($stored->oauth_refresh_token));
        $this->assertArrayNotHasKey('oauth_access_token', $user->toArray());
        $this->assertArrayNotHasKey('oauth_refresh_token', $user->toArray());
        $this->assertSame($baseUrl.'/oauth/token', (string) $this->history[0]['request']->getUri());
        parse_str((string) $this->history[0]['request']->getBody(), $tokenRequest);
        $this->assertSame([
            'grant_type' => 'authorization_code', 'client_id' => 'test-client',
            'client_secret' => 'test-secret', 'code' => 'test-code',
            'redirect_uri' => url('/auth/'.$provider.'/callback'),
        ], $tokenRequest);
        $this->assertSame($baseUrl.'/api/user', (string) $this->history[1]['request']->getUri());
        $this->assertSame('Bearer access-token', $this->history[1]['request']->getHeaderLine('Authorization'));
        $this->assertCount(2, $this->history);

        Auth::forgetGuards();
        $this->get(route('profile.edit'))->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.cid', 1234567));
    }

    public function test_replayed_callback_cannot_authenticate_again(): void
    {
        $this->fakeProvider('vatsim', $this->profile());
        $callback = route('oauth.callback', ['provider' => 'vatsim', 'state' => 'test-state', 'code' => 'test-code']);
        $this->withSession(['state' => 'test-state', 'oauth.provider' => 'vatsim'])
            ->get($callback)->assertRedirect(route('dashboard'));
        Auth::logout();

        $this->get($callback)->assertRedirect(route('login'))->assertSessionHasErrors('oauth');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->assertCount(2, $this->history);
    }

    public function test_invalid_profile_does_not_overwrite_an_existing_account(): void
    {
        $user = User::factory()->create(['cid' => 1234567, 'oauth_access_token' => 'existing-token'])->refresh();
        $profile = $this->profile();
        $profile['personal']['email'] = null;
        $this->fakeProvider('vatsim', $profile);

        $this->withSession(['state' => 'test-state', 'oauth.provider' => 'vatsim'])
            ->get(route('oauth.callback', ['provider' => 'vatsim', 'state' => 'test-state', 'code' => 'test-code']))
            ->assertRedirect(route('login'))->assertSessionHasErrors('oauth');

        $this->assertGuest();
        $this->assertSame($user->getAttributes(), $user->fresh()->getAttributes());
    }

    public function test_existing_cid_is_updated_across_providers_and_returns_to_intended_page(): void
    {
        User::factory()->create([
            'cid' => 1234567, 'oauth_provider' => 'vatsim',
            'oauth_access_token' => 'old-token', 'oauth_refresh_token' => 'old-refresh-token',
            'oauth_expires_at' => now()->addDay(),
        ]);
        $profile = $this->profile();
        $profile['vatsim']['division']['id'] = null;
        $profile['vatsim']['subdivision']['id'] = null;
        $this->fakeProvider('handover', $profile, ['access_token' => 'new-token']);

        $response = $this->withSession([
            'state' => 'test-state', 'oauth.provider' => 'handover', 'url.intended' => route('profile.edit'),
        ])->get(route('oauth.callback', ['provider' => 'handover', 'state' => 'test-state', 'code' => 'test-code']));

        $response->assertRedirect(route('profile.edit'));
        $this->assertDatabaseCount('users', 1);
        $user = User::query()->findOrFail(1234567);
        $this->assertAuthenticatedAs($user);
        $this->assertSame('handover', $user->oauth_provider);
        $this->assertSame('Test Controller', $user->name_full);
        $this->assertNull($user->division);
        $this->assertNull($user->subdivision);
        $this->assertSame('new-token', $user->oauth_access_token);
        $this->assertNull($user->oauth_refresh_token);
        $this->assertNull($user->oauth_expires_at);
    }

    public function test_matching_email_does_not_link_different_cids(): void
    {
        $existing = User::factory()->create(['email' => 'controller@example.com']);
        $this->fakeProvider('vatsim', $this->profile());

        $this->withSession(['state' => 'test-state', 'oauth.provider' => 'vatsim'])
            ->get(route('oauth.callback', ['provider' => 'vatsim', 'state' => 'test-state', 'code' => 'test-code']))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseCount('users', 2);
        $this->assertAuthenticatedAs(User::query()->findOrFail(1234567));
        $this->assertModelExists($existing);
    }

    #[TestWith([null])]
    #[TestWith(['wrong-state'])]
    public function test_invalid_state_does_not_contact_provider_or_create_user(?string $state): void
    {
        $this->fakeProvider('vatsim', $this->profile());

        $this->withSession(['state' => 'test-state', 'oauth.provider' => 'vatsim'])
            ->get(route('oauth.callback', ['provider' => 'vatsim', 'state' => $state, 'code' => 'test-code']))
            ->assertRedirect(route('login'))->assertSessionHasErrors('oauth')->assertSessionMissing('state');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertCount(0, $this->history);
    }

    #[TestWith(['handover', ['state' => 'test-state', 'code' => 'test-code']])]
    #[TestWith(['vatsim', ['state' => 'test-state', 'error' => 'access_denied']])]
    #[TestWith(['vatsim', ['state' => 'test-state']])]
    #[TestWith(['vatsim', ['state' => ['invalid'], 'code' => 'test-code']])]
    #[TestWith(['vatsim', ['state' => 'test-state', 'code' => ['invalid']]])]
    public function test_invalid_callback_is_rejected_before_token_exchange(string $provider, array $query): void
    {
        $this->fakeProvider($provider, $this->profile());

        $this->withSession(['state' => 'test-state', 'oauth.provider' => 'vatsim'])
            ->get(route('oauth.callback', ['provider' => $provider, ...$query]))
            ->assertRedirect(route('login'))->assertSessionHasErrors('oauth')
            ->assertSessionMissing('state')->assertSessionMissing('oauth.provider');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertCount(0, $this->history);
    }

    #[TestWith(['cid', 'not-a-cid'])]
    #[TestWith(['cid', 0])]
    #[TestWith(['cid', '01234567'])]
    #[TestWith(['personal.name_full', null])]
    #[TestWith(['personal.email', 'invalid'])]
    #[TestWith(['vatsim.rating.id', null])]
    #[TestWith(['vatsim.division.id', ['invalid']])]
    #[TestWith(['oauth.token_valid', 'false'])]
    public function test_invalid_profiles_do_not_create_accounts(string $path, mixed $value): void
    {
        $profile = $this->profile();
        data_set($profile, $path, $value);
        $this->fakeProvider('vatsim', $profile);

        $this->withSession(['state' => 'test-state', 'oauth.provider' => 'vatsim'])
            ->get(route('oauth.callback', ['provider' => 'vatsim', 'state' => 'test-state', 'code' => 'test-code']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['oauth' => 'Unable to sign in with this provider. Please try again.']);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    #[TestWith(['unavailable'])]
    #[TestWith(['connection'])]
    #[TestWith(['invalid-json'])]
    #[TestWith(['missing-token'])]
    public function test_provider_failures_return_a_retryable_error(string $failure): void
    {
        $response = match ($failure) {
            'unavailable' => new Response(503, [], 'secret-provider-error'),
            'connection' => new ConnectException('connection failed', new Request('POST', 'https://auth.vatsim.net/oauth/token')),
            'invalid-json' => new Response(200, [], 'not-json'),
            'missing-token' => new Response(200, [], '{"expires_in":3600}'),
        };
        $this->fakeResponses('vatsim', [$response]);

        $this->withSession(['state' => 'test-state', 'oauth.provider' => 'vatsim'])
            ->get(route('oauth.callback', ['provider' => 'vatsim', 'state' => 'test-state', 'code' => 'test-code']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['oauth' => 'Unable to sign in with this provider. Please try again.']);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertCount(1, $this->history);
    }

    #[TestWith(['github'])]
    #[TestWith(['handover'])]
    public function test_unknown_or_disabled_providers_return_404(string $provider): void
    {
        config(['services.handover.enabled' => false]);

        $this->get(route('oauth.redirect', $provider))->assertNotFound();
        $this->get(route('oauth.callback', $provider))->assertNotFound();

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_oauth_redirect_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->get(route('oauth.redirect', 'vatsim'))->assertRedirect();
        }

        $this->get(route('oauth.redirect', 'vatsim'))->assertTooManyRequests();
    }

    private function profile(): array
    {
        return [
            'cid' => '1234567',
            'personal' => ['name_full' => 'Test Controller', 'email' => 'controller@example.com'],
            'vatsim' => [
                'rating' => ['id' => 5],
                'division' => ['id' => 'EUD'],
                'subdivision' => ['id' => 'SCA'],
            ],
            'oauth' => ['token_valid' => 'true'],
        ];
    }

    private function fakeProvider(string $provider, array $profile, ?array $tokens = null): void
    {
        $this->fakeResponses($provider, [
            new Response(200, [], json_encode($tokens ?? [
                'access_token' => 'access-token', 'refresh_token' => 'refresh-token', 'expires_in' => 3600,
            ], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['data' => $profile], JSON_THROW_ON_ERROR)),
        ]);
    }

    private function fakeResponses(string $provider, array $responses): void
    {
        $handler = HandlerStack::create(new MockHandler($responses));
        $handler->push(Middleware::history($this->history));
        $client = new Client(['handler' => $handler]);
        $this->app->resolving(OAuthController::class, function () use ($provider, $client): void {
            Socialite::forgetDrivers();
            Socialite::driver($provider)->setHttpClient($client);
        });
    }
}
