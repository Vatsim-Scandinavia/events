<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staffing extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'description',
        'channel_id',
        'message_id',
        'section_1_title',
        'section_2_title',
        'section_3_title',
        'section_4_title',
        'event_instance_id',
    ];

    public function event() 
    {
        return $this->belongsTo(Event::class);
    }

    public function positions() 
    {
        return $this->hasMany(Position::class);
    }

    public function instance()
    {
        return $this->belongsTo(EventInstance::class, 'event_instance_id');
    }

    public function getEventTitleAttribute()
    {
        return $this->instance->event->title ?? 'Unknown Event';
    }
}
