<?php

namespace App\Socialite;

use Illuminate\Support\Arr;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

class VatsimProvider extends AbstractProvider implements ProviderInterface
{
    protected $scopes = ['full_name', 'email'];
    protected $scopeSeparator = ' ';

    public function getBaseUrl(): string
    {
        return config('services.vatsim.base_url');
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->getBaseUrl() . '/oauth/authorize', $state);
    }

    protected function getTokenUrl(): string
    {
        return $this->getBaseUrl() . '/oauth/token';
    }

    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get($this->getBaseUrl() . '/api/user', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    protected function mapUserToObject(array $user): User
    {
        return (new User())->setRaw($user)->map([
            'id' => Arr::get($user, 'data.cid'),
            'email' => Arr::get($user, 'data.personal.email'),
            'first_name' => Arr::get($user, 'data.personal.name_first'),
            'last_name' => Arr::get($user, 'data.personal.name_last'),
        ]);
    }
}