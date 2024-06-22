<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillables = [
        'id',
        'title',
        'description',
        'start_date',
        'end_date',
        'recurring',
        'image'
    ];

    public function organizer()
    {
        return $this->belongsTo(User::class);
    }
}
