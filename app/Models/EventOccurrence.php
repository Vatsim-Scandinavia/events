<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventOccurrence extends Model
{
    /** @use HasFactory<\Database\Factories\EventOccurrenceFactory> */
    use HasFactory;

    protected $table = 'event_occurrences';
    public $timestamps = true;

    protected $fillable = [
        'event_id',
        'start_time',
        'end_time',
        'status',
        'notified_at',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

}
