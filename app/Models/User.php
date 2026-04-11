<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    public $timestamps = false;
    protected $table = 'users';

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

    protected $hidden = [
        'remember_token',
        'access_token',
        'refresh_token',
        'token_expires',
    ];

    protected function casts(): array
    {
        return [
            'last_login' => 'datetime',
        ];
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'created_by');
    }

    public function calendars()
    {
        return $this->hasMany(Calendar::class, 'created_by');
    }
}
