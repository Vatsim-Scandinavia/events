<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staffing extends Model
{
    /** @use HasFactory<\Database\Factories\StaffingFactory> */
    use HasFactory;

    public $timestamps = true;
    protected $table = 'staffings';

    protected $fillable = [
        'event_id',
        'discord_channel_id',
        'discord_message_id',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function sections()
    {
        return $this->hasMany(StaffingSection::class)->orderBy('order');
    }

    // Convenience: all positions across all sections of this staffing
    public function positions()
    {
        return $this->hasManyThrough(StaffingPosition::class, StaffingSection::class, 'staffing_id', 'section_id');
    }
}
