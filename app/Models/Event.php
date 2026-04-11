<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use HasFactory;

    protected $table = 'events';
    public $timestamps = true;

    protected $fillable = [
        'calendar_id',
        'title',
        'slug',
        'short_description',
        'long_description',
        'featured_airports',
        'banner_path',
        'status',
        'recurrence_rule',
        'timezone',
        'discord_channel_id',
        'discord_message_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'featured_airports' => 'array',
        ];
    }

    public function calendar()
    {
        return $this->belongsTo(Calendar::class);
    }

    public function occurrences()
    {
        return $this->hasMany(EventOccurrence::class);
    }
}
