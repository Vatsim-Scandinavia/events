<?php

namespace App\Auth;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;
use UnexpectedValueException;

class VatsimProvider extends AbstractProvider
{
    protected $scopes = ['full_name', 'email'];

    protected $scopeSeparator = ' ';

    protected string $baseUrl;

    public function setBaseUrl(string $url): static
    {
        $this->baseUrl = rtrim($url, '/');

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

    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get($this->baseUrl.'/api/user', [
            'headers' => ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token],
        ]);

        $user = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($user)) {
            throw new UnexpectedValueException('Invalid OAuth user response.');
        }

        return $user;
    }

    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id' => data_get($user, 'data.cid'),
            'name' => data_get($user, 'data.personal.name_full'),
            'email' => data_get($user, 'data.personal.email'),
        ]);
    }
}
