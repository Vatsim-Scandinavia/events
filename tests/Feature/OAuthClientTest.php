<?php

namespace Tests\Feature;

use App\Auth\OAuthClient;
use App\Auth\OAuthRequestFailed;
use App\Enums\SignInProvider;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OAuthClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.vatsim' => ['client_id' => 'hub', 'client_secret' => 's3cret', 'redirect' => 'https://hub.example/auth/vatsim/callback', 'base_url' => 'https://auth.vatsim.net/'],
            'services.handover' => ['client_id' => 'hub-ho', 'client_secret' => 'ho-s3cret', 'redirect' => 'https://hub.example/auth/handover/callback', 'base_url' => 'https://handover.vatsim-scandinavia.org'],
        ]);
    }

    public function test_the_authorize_url_asks_for_the_scopes_with_state_and_an_s256_challenge(): void
    {
        $verifier = OAuthClient::newCodeVerifier();
        $url = (new OAuthClient(SignInProvider::Vatsim))->authorizeUrl('the-state', $verifier);

        $this->assertStringStartsWith('https://auth.vatsim.net/oauth/authorize?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame([
            'response_type' => 'code',
            'client_id' => 'hub',
            'redirect_uri' => 'https://hub.example/auth/vatsim/callback',
            'scope' => 'full_name vatsim_details email',
            'state' => 'the-state',
            'code_challenge' => OAuthClient::codeChallenge($verifier),
            'code_challenge_method' => 'S256',
        ], $query);
        $this->assertSame(43, strlen($verifier));
        // RFC 7636 appendix B.
        $this->assertSame('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', OAuthClient::codeChallenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'));
    }

    public function test_handover_is_the_same_flow_on_its_own_server_with_its_own_client(): void
    {
        $url = (new OAuthClient(SignInProvider::Handover))->authorizeUrl('state', OAuthClient::newCodeVerifier());

        $this->assertStringStartsWith('https://handover.vatsim-scandinavia.org/oauth/authorize?', $url);
        $this->assertStringContainsString('client_id=hub-ho', $url);
    }

    public function test_exchanging_a_code_sends_the_verifier_and_client_secret_and_returns_the_tokens(): void
    {
        $this->freezeTime();
        Http::fake(['auth.vatsim.net/oauth/token' => Http::response(['token_type' => 'Bearer', 'expires_in' => 604800, 'access_token' => 'access', 'refresh_token' => 'refresh', 'scopes' => ['full_name']])]);

        $tokens = (new OAuthClient(SignInProvider::Vatsim))->exchangeCode('the-code', 'the-verifier');

        $this->assertSame(['access', 'refresh'], [$tokens->accessToken, $tokens->refreshToken]);
        $this->assertTrue($tokens->expiresAt->equalTo(CarbonImmutable::now()->addDays(7)));
        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->data() === [
            'grant_type' => 'authorization_code',
            'code' => 'the-code',
            'redirect_uri' => 'https://hub.example/auth/vatsim/callback',
            'code_verifier' => 'the-verifier',
            'client_id' => 'hub',
            'client_secret' => 's3cret',
        ]);
    }

    public function test_refreshing_returns_the_rotated_tokens_and_keeps_the_old_refresh_token_when_none_comes_back(): void
    {
        Http::fakeSequence('auth.vatsim.net/oauth/token')
            ->push(['expires_in' => 60, 'access_token' => 'access-2', 'refresh_token' => 'refresh-2'])
            ->push(['expires_in' => 60, 'access_token' => 'access-3']);
        $client = new OAuthClient(SignInProvider::Vatsim);

        $this->assertSame('refresh-2', $client->refresh('refresh-1')->refreshToken);
        $this->assertSame('refresh-2', $client->refresh('refresh-2')->refreshToken);
        Http::assertSent(fn (Request $request) => ($request->data()['grant_type'] ?? null) === 'refresh_token' && $request->data()['refresh_token'] === 'refresh-1');
    }

    public function test_the_member_is_the_data_of_api_user_read_with_the_access_token(): void
    {
        Http::fake(['auth.vatsim.net/api/user' => Http::response(['data' => ['cid' => '10000010', 'vatsim' => ['rating' => ['id' => 12]]]])]);

        $member = (new OAuthClient(SignInProvider::Vatsim))->member('access');

        $this->assertSame('10000010', $member['cid']);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer access') && $request->hasHeader('Accept', 'application/json'));
    }

    public function test_a_refusal_is_permanent_and_an_outage_is_not(): void
    {
        $client = new OAuthClient(SignInProvider::Vatsim);

        Http::fakeSequence('auth.vatsim.net/oauth/token')
            // Connect answers a revoked refresh token with 401 invalid_request, not invalid_grant.
            ->push(['error' => 'invalid_request', 'hint' => 'Token has been revoked'], 401)
            ->push('Bad gateway', 502)
            ->pushFailedConnection('cURL error 28');

        $refused = $this->failure(fn () => $client->refresh('revoked'));
        $this->assertTrue($refused->isPermanent());
        $this->assertSame([401, 'invalid_request'], [$refused->status, $refused->error]);

        $this->assertFalse($this->failure(fn () => $client->refresh('refresh'))->isPermanent());

        $timeout = $this->failure(fn () => $client->exchangeCode('code', 'verifier'));
        $this->assertNull($timeout->status);
        $this->assertFalse($timeout->isPermanent());
    }

    public function test_an_answer_without_an_access_token_is_a_failure(): void
    {
        Http::fake(['auth.vatsim.net/oauth/token' => Http::response(['token_type' => 'Bearer'])]);

        $this->assertSame('invalid_response', $this->failure(fn () => (new OAuthClient(SignInProvider::Vatsim))->exchangeCode('code', 'verifier'))->error);
    }

    /**
     * @param  callable(): mixed  $call
     */
    private function failure(callable $call): OAuthRequestFailed
    {
        try {
            $call();
        } catch (OAuthRequestFailed $failure) {
            return $failure;
        }

        $this->fail('Expected the request to fail.');
    }
}
