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
        'last_login'
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
        return $this->belongsToMany(Group::class, 'permissions')->withPivot('area_id')->withTimestamps();
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
    public function isModerator(?Area $area = null) 
    {
        if($area == null) {
            return $this->groups->where('id', 2)->isNotEmpty();
        }

        foreach($this->groups->where('id', 2) as $group) {
            if($group->pivot->area_id == $area->id) {
                return true;
            }
        }

        return false;
    }

    public function isModeratorOrAbove(?Area $area = null) 
    {
        if ($area == null) {
            return $this->groups->where('id', '<=', 2)->isNotEmpty();
        }

        if ($this->isAdmin()) {
            return true;
        }

        // Check if user is moderator or above in the specified area
        foreach ($this->groups->where('id', '<=', 2) as $group) {
            if ($group->pivot->area_id == $area->id) {
                return true;
            }
        }

        return false;
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
