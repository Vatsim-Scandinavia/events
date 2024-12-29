<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'email',
        'first_name',
        'last_name',
        'last_login',
        'access_token',
        'refresh_token',
        'token_expires',
    ];

    public $timestamps = false;

    protected $dates = [
        'last_login',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'remember_token',
    ];

    /**
     * Relationship of all permissions to this user
     *
     * @return Illuminate\Database\Eloquent\Collection|Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function groups()
    {
        return $this->belongsToMany(Group::class, 'permissions');
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function getNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Return if user is a moderator
     *
     * @return bool
     */
    public function isModerator()
    {
        return $this->groups->where('id', 2)->isNotEmpty();
    }

    /**
     * Return if user is a moderator or above
     *
     * @return bool
     */
    public function isModeratorOrAbove()
    {
        return $this->groups->where('id', '<=', 2)->isNotEmpty();
    }

    /**
     * Return if user is an admin
     *
     * @return bool
     */
    public function isAdmin()
    {
        return $this->groups->contains('id', 1);
    }
}
