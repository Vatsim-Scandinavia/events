<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Calendar extends Model
{
    /** @use HasFactory<\Database\Factories\CalendarFactory> */
    use HasFactory;

    public $timestamps = true;
    protected $table = 'calendars';

    protected $fillable = [
        'id',
        'title',
        'description',
        'visibility',
        'created_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'calendar_id');
    }
}
