<?php

namespace App\Auth;

use App\Enums\SignInProvider;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The OAuth2 authorization-code flow against a Sign-in Provider (VATSIM Connect, or Handover,
 * which offers the same endpoints): only the protocol, nothing about Accounts or sessions.
 *
 * It always sends PKCE (S256), never retries a code exchange (codes work once) and never follows
 * redirects. Everything goes through Laravel's HTTP client, whose ordinary User-Agent gets past
 * the Cloudflare check in front of Connect, and which tests fake with Http::fake.
 */
final class OAuthClient
{
    /** The scopes asked for; vatsim_details is the one sign-in can't do without. */
    public const array SCOPES = ['full_name', 'vatsim_details', 'email'];

    private SignInProvider $provider;

    public function __construct(?SignInProvider $provider = null)
    {
        $this->provider = $provider ?? SignInProvider::configured();
    }

    /**
     * A new random PKCE code verifier, 43 URL-safe characters (32 bytes).
     */
    public static function newCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * The S256 challenge sent with the authorization request for a verifier.
     */
    public static function codeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /**
     * Where to send someone to sign in.
     */
    public function authorizeUrl(string $state, string $codeVerifier): string
    {
        return $this->provider->baseUrl().'/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->provider->clientId(),
            'redirect_uri' => $this->provider->redirectUri(),
            'scope' => implode(' ', self::SCOPES),
            'state' => $state,
            'code_challenge' => self::codeChallenge($codeVerifier),
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Swap the code the provider sent back for tokens.
     *
     * @throws OAuthRequestFailed
     */
    public function exchangeCode(string $code, string $codeVerifier): TokenSet
    {
        return $this->tokens([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->provider->redirectUri(),
            'code_verifier' => $codeVerifier,
        ]);
    }

    /**
     * Renew the tokens. Providers rotate refresh tokens, so the old one stops working once this
     * returns; if one ever sends no new refresh token, the old one is kept.
     *
     * @throws OAuthRequestFailed
     */
    public function refresh(string $refreshToken): TokenSet
    {
        return $this->tokens(['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken], $refreshToken);
    }

    /**
     * The member the access token belongs to: the `data` of /api/user, holding `cid`, `personal`
     * and `vatsim`, for the scopes they shared.
     *
     * @return array<string, mixed>
     *
     * @throws OAuthRequestFailed
     */
    public function member(string $accessToken): array
    {
        $response = $this->send(fn () => $this->http()->withToken($accessToken)->get($this->provider->baseUrl().'/api/user'));
        $data = $response->json('data');

        if (! is_array($data)) {
            throw new OAuthRequestFailed($response->status(), 'invalid_response');
        }

        return $data;
    }

    /**
     * @param  array<string, string>  $fields
     *
     * @throws OAuthRequestFailed
     */
    private function tokens(array $fields, ?string $currentRefreshToken = null): TokenSet
    {
        $response = $this->send(fn () => $this->http()->asForm()->post($this->provider->baseUrl().'/oauth/token', [
            ...$fields,
            'client_id' => $this->provider->clientId(),
            'client_secret' => $this->provider->clientSecret(),
        ]));

        $accessToken = $response->json('access_token');

        if (! is_string($accessToken) || $accessToken === '') {
            throw new OAuthRequestFailed($response->status(), 'invalid_response');
        }

        $refreshToken = $response->json('refresh_token');

        return new TokenSet(
            accessToken: $accessToken,
            refreshToken: is_string($refreshToken) && $refreshToken !== '' ? $refreshToken : $currentRefreshToken,
            expiresAt: CarbonImmutable::now()->addSeconds(max(0, (int) $response->json('expires_in', 0))),
        );
    }

    /**
     * @param  callable(): Response  $request
     *
     * @throws OAuthRequestFailed
     */
    private function send(callable $request): Response
    {
        try {
            $response = $request();
        } catch (ConnectionException) {
            throw new OAuthRequestFailed(null);
        }

        if (! $response->successful()) {
            $error = $response->json('error');

            throw new OAuthRequestFailed($response->status(), is_string($error) ? Str::limit($error, 50, '') : null);
        }

        return $response;
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()->connectTimeout(5)->timeout(10)->withoutRedirecting();
    }
}
