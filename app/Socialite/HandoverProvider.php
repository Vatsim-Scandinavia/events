<?php

namespace App\Socialite;

use App\Enums\ControllerRating;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\Token;
use Laravel\Socialite\Two\User;

/**
 * VATSIM Scandinavia's Handover as a Socialite driver (`handover`), with S256 PKCE. Handover
 * insists on every scope at its own sign-in, and answers a scope it wasn't given with nulls.
 *
 * The mapped user carries the Member Details as attributes: `firstName`, `rating` (null for one
 * the Hub doesn't know), `subdivision`, and `isComplete`, whether everything the Hub needs came
 * back, a token Handover vouches for included. Requests go through Laravel's HTTP handler stack,
 * so Http::fake covers them in tests.
 */
class HandoverProvider extends AbstractProvider
{
    /** @var list<string> */
    protected $scopes = ['full_name', 'vatsim_details', 'email'];

    protected $scopeSeparator = ' ';

    protected $usesPKCE = true;

    protected $encodingType = PHP_QUERY_RFC3986;

    /**
     * @param  string|null  $state
     */
    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->baseUrl().'/oauth/authorize', $state);
    }

    /**
     * Renew the tokens. Refresh tokens rotate, so the old one stops working once this returns; if
     * none comes back the old one is kept, where Socialite's own refresh would fail.
     *
     * @param  string  $refreshToken
     */
    public function refreshToken($refreshToken): Token
    {
        $response = $this->getRefreshTokenResponse($refreshToken);
        $rotated = Arr::get($response, 'refresh_token');

        return new Token(
            (string) Arr::get($response, 'access_token'),
            is_string($rotated) && $rotated !== '' ? $rotated : $refreshToken,
            (int) Arr::get($response, 'expires_in', 0),
            explode($this->scopeSeparator, (string) Arr::get($response, 'scope', '')),
        );
    }

    protected function getTokenUrl(): string
    {
        return $this->baseUrl().'/oauth/token';
    }

    /**
     * @param  string  $token
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get($this->baseUrl().'/api/user', [
            RequestOptions::HEADERS => ['Accept' => 'application/json', 'Authorization' => "Bearer {$token}"],
        ]);

        $data = json_decode((string) $response->getBody(), true)['data'] ?? null;

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<string, mixed>  $user  The `data` of /api/user; Handover sends the CID as a number.
     */
    protected function mapUserToObject(array $user): User
    {
        $cid = Arr::get($user, 'cid');
        $rating = Arr::get($user, 'vatsim.rating.id');
        $name = self::text(Arr::get($user, 'personal.name_full'));
        $email = self::text(Arr::get($user, 'personal.email'));
        $tokenValid = Arr::get($user, 'oauth.token_valid');

        return (new User)->setRaw($user)->map([
            'id' => is_numeric($cid) && (int) $cid > 0 ? (int) $cid : null,
            'name' => $name,
            'email' => $email,
            'firstName' => self::text(Arr::get($user, 'personal.name_first')),
            'rating' => is_numeric($rating) ? ControllerRating::tryFrom((int) $rating) : null,
            'subdivision' => self::text(Arr::get($user, 'vatsim.subdivision.id')),
            'isComplete' => is_numeric($cid) && is_numeric($rating) && $name !== null && $email !== null && ($tokenValid === true || $tokenValid === 'true'),
        ]);
    }

    protected function getHttpClient(): Client
    {
        return $this->httpClient ??= new Client([
            'handler' => Http::buildHandlerStack(),
            RequestOptions::CONNECT_TIMEOUT => 5,
            RequestOptions::TIMEOUT => 10,
            RequestOptions::ALLOW_REDIRECTS => false,
        ]);
    }

    /**
     * Without a trailing slash.
     */
    private function baseUrl(): string
    {
        return rtrim((string) config('services.handover.base_url'), '/');
    }

    private static function text(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }
}
