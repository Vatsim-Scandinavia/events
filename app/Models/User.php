<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $cid
 * @property string $name_full
 * @property string $email
 * @property int $controller_rating
 * @property string|null $division
 * @property string|null $subdivision
 * @property string|null $oauth_provider
 * @property string|null $oauth_id
 * @property string|null $oauth_access_token
 * @property string|null $oauth_refresh_token
 * @property Carbon|null $oauth_expires_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['cid', 'name_full', 'email', 'controller_rating', 'division', 'subdivision', 'oauth_provider', 'oauth_id', 'oauth_access_token', 'oauth_refresh_token', 'oauth_expires_at'])]
#[Hidden(['oauth_access_token', 'oauth_refresh_token', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $primaryKey = 'cid';

    public $incrementing = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'controller_rating' => 'integer',
            'oauth_access_token' => 'encrypted',
            'oauth_refresh_token' => 'encrypted',
            'oauth_expires_at' => 'datetime',
        ];
    }
}
