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

    protected function casts(): array
    {
        return [
            'start_time'   => 'datetime',
            'end_time'     => 'datetime',
            'notified_at'  => 'datetime',
        ];
    }

    public function scopeFuture($query)
    {
        return $query->where('start_time', '>=', now());
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }
}
