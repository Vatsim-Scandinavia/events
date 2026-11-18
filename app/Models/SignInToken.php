<?php

namespace App\Models;

use App\Enums\SignInProvider;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * The tokens a Sign-in Provider issued an Account, kept so the daily sync can read fresh Member
 * Details with them. One pair per Account, encrypted, never in the session.
 *
 * Signing out leaves them alone. Neither provider can revoke tokens, so a refusal from the provider
 * or deleting the Account are the only things that remove them.
 */
class SignInToken extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'provider', 'access_token', 'refresh_token', 'expires_at'];

    /**
     * Never sent anywhere: not to the browser, not into logs.
     *
     * @var list<string>
     */
    protected $hidden = ['access_token', 'refresh_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => SignInProvider::class,
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Keep the tokens the member just signed in with, replacing any earlier ones.
     */
    public static function keep(User $user, SignInProvider $provider, SocialiteUser $member): self
    {
        return self::query()->updateOrCreate(['user_id' => $user->id], [
            'provider' => $provider,
            'access_token' => $member->token,
            'refresh_token' => $member->refreshToken,
            'expires_at' => now()->addSeconds(max(0, (int) $member->expiresIn)),
        ]);
    }

    /**
     * An access token that works now, refreshed first when it has (nearly) expired or when $refresh
     * (the provider just turned it down). Null, and the tokens deleted, when the provider refuses to
     * refresh them.
     *
     * @throws TransferException when the provider is down or rate limiting
     */
    public function freshAccessToken(bool $refresh = false): ?string
    {
        if (! $refresh && $this->expires_at->isAfter(now()->addMinutes(5))) {
            return $this->access_token;
        }

        if ($this->refresh_token === null) {
            $this->delete();

            return null;
        }

        try {
            $tokens = Socialite::driver($this->provider->value)->refreshToken($this->refresh_token);
        } catch (ClientException $refused) {
            if ($refused->getResponse()->getStatusCode() === 429) {
                throw $refused;
            }

            $this->delete();

            return null;
        }

        $this->update([
            'access_token' => $tokens->token,
            'refresh_token' => $tokens->refreshToken,
            'expires_at' => now()->addSeconds(max(0, (int) $tokens->expiresIn)),
        ]);

        return $this->access_token;
    }
}
