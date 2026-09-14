<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class OAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['oauth.provider' => 'vatsim', 'services.vatsim.client_id' => 'client', 'services.vatsim.client_secret' => 'secret', 'services.vatsim.base_url' => 'https://auth.vatsim.net']);
    }

    public function test_login_displays_only_the_selected_provider(): void
    {
        config(['oauth.provider' => 'handover', 'services.handover.client_id' => 'client', 'services.handover.client_secret' => 'secret', 'services.handover.base_url' => 'https://handover.example']);
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->component('auth/login')->where('providerName', 'Handover')->where('configured', true));
        $this->get('/auth/vatsim/redirect')->assertNotFound();
    }

    public function test_redirect_uses_state_and_the_configured_provider(): void
    {
        $response = $this->get(route('oauth.redirect'));
        $response->assertRedirect();
        $url = $response->headers->get('Location');
        $this->assertStringStartsWith('https://auth.vatsim.net/oauth/authorize?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertNotEmpty($query['state']);
        $this->assertSame('full_name email', $query['scope']);
        $response->assertSessionHas('state', $query['state']);
        $response->assertSessionHas('oauth_provider', 'vatsim');
    }

    public function test_callback_creates_a_passwordless_user_and_logs_in(): void
    {
        $this->mockIdentity();
        $this->withSession(['oauth_provider' => 'vatsim', 'url.intended' => route('profile.edit')])
            ->get(route('oauth.callback', ['code' => 'code', 'state' => 'state']))->assertRedirect(route('profile.edit'));
        $user = User::where('vatsim_cid', '1234567')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->password);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('Pilot Name', $user->name);
    }

    public function test_returning_users_are_matched_by_cid_and_profile_is_refreshed(): void
    {
        $user = User::factory()->create(['vatsim_cid' => '1234567', 'email' => 'old@example.com']);
        $this->mockIdentity();
        $this->withSession(['oauth_provider' => 'vatsim'])->get(route('oauth.callback'))->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame('pilot@example.com', $user->fresh()->email);
    }

    public function test_matching_email_never_links_an_unrelated_account(): void
    {
        $user = User::factory()->create(['email' => 'pilot@example.com']);
        $this->mockIdentity();
        $this->withSession(['oauth_provider' => 'vatsim'])->get(route('oauth.callback'))->assertRedirect(route('login'))->assertSessionHas('oauth_error');
        $this->assertGuest();
        $this->assertNull($user->fresh()->vatsim_cid);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_missing_identity_data_is_rejected(): void
    {
        $this->mockIdentity(['id' => null, 'email' => null]);
        $this->withSession(['oauth_provider' => 'vatsim'])->get(route('oauth.callback'))->assertRedirect(route('login'))->assertSessionHas('oauth_error');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_invalid_state_is_rejected_by_socialite(): void
    {
        $this->withSession(['oauth_provider' => 'vatsim', 'state' => 'expected'])->get(route('oauth.callback', ['state' => 'wrong', 'code' => 'code']))->assertRedirect(route('login'))->assertSessionHas('oauth_error');
        $this->assertGuest();
    }

    public function test_denial_and_callbacks_from_a_previous_provider_are_rejected(): void
    {
        Socialite::shouldReceive('driver')->never();
        $this->withSession(['oauth_provider' => 'vatsim'])->get(route('oauth.callback', ['error' => 'access_denied']))->assertRedirect(route('login'));
        $this->withSession(['oauth_provider' => 'handover'])->get(route('oauth.callback'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_unconfigured_authentication_is_unavailable(): void
    {
        config(['services.vatsim.client_secret' => null]);
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->component('auth/login')->where('configured', false));
        $this->get(route('oauth.redirect'))->assertStatus(503);
    }

    public function test_local_password_authentication_routes_are_removed(): void
    {
        foreach (['/register', '/forgot-password', '/two-factor-challenge', '/confirm-password'] as $uri) {
            $this->get($uri)->assertNotFound();
        }
        $this->post('/login')->assertStatus(405);
        $this->actingAs(User::factory()->create())->get('/settings/security')->assertNotFound();
        $this->put('/settings/password')->assertNotFound();
    }

    public function test_logout_invalidates_the_authenticated_session(): void
    {
        $this->actingAs(User::factory()->create())->withSession(['example' => 'value'])
            ->post(route('logout'))->assertRedirect(route('home'))->assertSessionMissing('example');
        $this->assertGuest();
    }

    public function test_provider_outages_return_to_login_without_authentication(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andThrow(new ConnectException('Unavailable', new Request('GET', 'https://auth.vatsim.net/api/user')));
        Socialite::shouldReceive('driver')->with('vatsim')->once()->andReturn($provider);
        $this->withSession(['oauth_provider' => 'vatsim'])->get(route('oauth.callback'))->assertRedirect(route('login'))->assertSessionHas('oauth_error');
        $this->assertGuest();
    }

    public function test_handover_callback_uses_the_selected_driver(): void
    {
        config(['oauth.provider' => 'handover', 'services.handover.client_id' => 'client', 'services.handover.client_secret' => 'secret', 'services.handover.base_url' => 'https://handover.example']);
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn((new SocialiteUser)->map(['id' => '1234567', 'name' => 'Pilot Name', 'email' => 'pilot@example.com']));
        Socialite::shouldReceive('driver')->with('handover')->once()->andReturn($provider);
        $this->withSession(['oauth_provider' => 'handover'])->get(route('oauth.callback'))->assertRedirect(route('home'));
        $this->assertAuthenticated();
    }

    private function mockIdentity(array $overrides = []): void
    {
        $identity = (new SocialiteUser)->map(array_merge(['id' => '1234567', 'name' => 'Pilot Name', 'email' => 'pilot@example.com'], $overrides));
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($identity);
        Socialite::shouldReceive('driver')->with('vatsim')->once()->andReturn($provider);
    }
}
