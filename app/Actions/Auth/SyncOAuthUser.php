<?php

namespace App\Actions\Auth;

use App\Actions\RecordAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Two\User as SocialiteUser;
use UnexpectedValueException;

class SyncOAuthUser
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(string $provider, SocialiteUser $identity): User
    {
        $attributes = [
            'cid' => $identity->getId(),
            'name_full' => $identity->getName(),
            'email' => $identity->getEmail(),
            'controller_rating' => $identity->attributes['controller_rating'] ?? null,
            'division' => $identity->attributes['division'] ?? null,
            'subdivision' => $identity->attributes['subdivision'] ?? null,
        ];

        if (Validator::make($attributes, [
            'cid' => ['required', 'integer', 'min:1', 'max:9007199254740991', 'regex:/^[1-9][0-9]*$/'],
            'name_full' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'controller_rating' => ['required', 'integer', 'min:0', 'max:32767'],
            'division' => ['nullable', 'string', 'max:16'],
            'subdivision' => ['nullable', 'string', 'max:16'],
        ])->fails()) {
            throw new UnexpectedValueException('The provider did not supply the required VATSIM profile.');
        }

        return DB::transaction(function () use ($attributes, $provider, $identity): User {
            $profile = [
                'name_full' => $attributes['name_full'],
                'email' => $attributes['email'],
                'controller_rating' => $attributes['controller_rating'],
                'division' => $attributes['division'],
                'subdivision' => $attributes['subdivision'],
                'oauth_provider' => $provider,
                'oauth_id' => (string) $identity->getId(),
                'oauth_access_token' => $identity->token,
                'oauth_refresh_token' => $identity->refreshToken,
                'oauth_expires_at' => $identity->expiresIn === null ? null : now()->addSeconds((int) $identity->expiresIn),
            ];
            $user = User::query()->firstOrCreate(['cid' => (int) $attributes['cid']], $profile);
            $fields = ['name_full', 'email', 'controller_rating', 'division', 'subdivision', 'oauth_provider'];
            $event = $user->wasRecentlyCreated ? 'created' : 'updated';
            $before = [];

            if (! $user->wasRecentlyCreated) {
                $user = User::whereKey($user->cid)->lockForUpdate()->firstOrFail();
                $before = $user->only($fields);
                $user->update($profile);
            }

            $this->audit->handle($user, $event, $before, $user->only($fields), 'oauth:'.$provider, $user);

            return $user;
        });
    }
}
