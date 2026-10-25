<?php

namespace App\Auth;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

/**
 * Socialite driver for VATSIM Connect, VATSIM's OAuth2 sign-in.
 *
 * The base URL is configurable so local installs can use the sandbox at auth-dev.vatsim.net.
 */
class VatsimConnectProvider extends AbstractProvider
{
    /**
     * @var array<int, string>
     */
    protected $scopes = ['full_name', 'vatsim_details', 'email'];

    protected $scopeSeparator = ' ';

    protected string $baseUrl = 'https://auth.vatsim.net';

    public function setBaseUrl(?string $baseUrl): static
    {
        if (filled($baseUrl)) {
            $this->baseUrl = rtrim($baseUrl, '/');
        }

        return $this;
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->baseUrl.'/oauth/authorize', $state);
    }

    protected function getTokenUrl(): string
    {
        return $this->baseUrl.'/oauth/token';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get($this->baseUrl.'/api/user', [
            RequestOptions::HEADERS => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        return json_decode((string) $response->getBody(), true)['data'] ?? [];
    }

    /**
     * @param  array{cid?: int|string, personal?: array{name_full?: string, email?: string}, vatsim?: array{rating?: array{id?: int}}}  $user
     */
    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id' => Arr::get($user, 'cid'),
            'name' => Arr::get($user, 'personal.name_full'),
            'email' => Arr::get($user, 'personal.email'),
        ]);
    }
}
