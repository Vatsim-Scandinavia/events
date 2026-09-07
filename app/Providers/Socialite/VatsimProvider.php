<?php

namespace App\Providers\Socialite;

use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;
use UnexpectedValueException;

class VatsimProvider extends AbstractProvider
{
    /** @var list<string> */
    protected $scopes = ['full_name', 'email', 'vatsim_details'];

    protected $scopeSeparator = ' ';

    protected function baseUrl(): string
    {
        return rtrim(config('services.vatsim.base_url'), '/');
    }

    protected function getAuthUrl(mixed $state): string
    {
        return $this->buildAuthUrlFromBase($this->baseUrl().'/oauth/authorize', $state);
    }

    protected function getTokenUrl(): string
    {
        return $this->baseUrl().'/oauth/token';
    }

    /** @return array<string, mixed> */
    public function getAccessTokenResponse(mixed $code): array
    {
        $response = parent::getAccessTokenResponse($code);

        if (! is_array($response) || Validator::make($response, [
            'access_token' => ['required', 'string'],
            'refresh_token' => ['nullable', 'string'],
            'expires_in' => ['nullable', 'integer', 'min:1', 'max:315360000'],
            'scope' => ['sometimes', 'string'],
        ])->fails()) {
            throw new UnexpectedValueException('The provider returned an invalid token response.');
        }

        return $response;
    }

    /** @return array<string, mixed> */
    protected function getUserByToken(mixed $token): array
    {
        $response = $this->getHttpClient()->get($this->baseUrl().'/api/user', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        $payload = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $user = data_get($payload, 'data');

        if (! is_array($user) || ! in_array(data_get($user, 'oauth.token_valid'), [true, 'true'], true)) {
            throw new UnexpectedValueException('The provider returned an invalid user response.');
        }

        return $user;
    }

    /** @param array<string, mixed> $user */
    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id' => data_get($user, 'cid'),
            'name' => data_get($user, 'personal.name_full'),
            'email' => data_get($user, 'personal.email'),
            'controller_rating' => data_get($user, 'vatsim.rating.id'),
            'division' => data_get($user, 'vatsim.division.id'),
            'subdivision' => data_get($user, 'vatsim.subdivision.id'),
        ]);
    }
}
