<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\EventInstance;


class Calendar extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'public',
    ];

    public function instances(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(EventInstance::class, Event::class);
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }
}
