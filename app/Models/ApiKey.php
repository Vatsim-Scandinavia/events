<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    protected $fillable = ['name', 'key', 'read_only', 'last_used_at'];

    protected $hidden = ['key'];

    protected function casts(): array
    {
        return [
            'read_only'    => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }
}
