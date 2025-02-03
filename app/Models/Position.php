<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'callsign',
        'booking_id',
        'discord_user',
        'section',
        'local_booking',
        'start_time',
        'end_time',
    ];

    public function staffing() 
    {
        return $this->belongsTo(Staffing::class);
    }
}
