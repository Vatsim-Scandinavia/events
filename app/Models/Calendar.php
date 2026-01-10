<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Calendar extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'public',
    ];

    public function instances()
    {
        return $this->hasManyThrough(EventInstance::class, Event::class);
    }
}
