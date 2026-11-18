<?php

namespace App\Models;

use App\Enums\SignInProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The tokens a Sign-in Provider issued an Account, kept to refresh its Member Details. Only
 * App\Auth\SignInTokens reads and writes them.
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
}
