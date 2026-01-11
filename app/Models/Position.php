<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Position extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'staffing_id',
        'callsign',
        'booking_id',
        'discord_user',
        'section',
        'local_booking',
        'start_time',
        'end_time',
    ];

    /**
     * Relationship to the parent Staffing.
     */
    public function staffing() 
    {
        return $this->belongsTo(Staffing::class);
    }

    /**
     * Get the user assigned to this position.
     */
    public function user(): BelongsTo
    {
        // This assumes your positions table has a 'user_id' column
        return $this->belongsTo(User::class);
    }
}