<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventInstance extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'event_id',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function staffing()
    {
        return $this->hasOne(Staffing::class, 'event_id');
    }
}
