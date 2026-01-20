<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffingPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'staffing_id',
        'position_id',
        'position_name',
        'is_local',
        'start_time',
        'end_time',
        'order',
        'booked_by_user_id',
        'discord_user_id',
        'vatsim_cid',
        'control_center_booking_id',
    ];

    protected $casts = [
        'is_local' => 'boolean',
        // start_time and end_time are now TIME columns (HH:MM:SS format)
        // They will be returned as strings like "18:00:00"
    ];

    public function staffing(): BelongsTo
    {
        return $this->belongsTo(Staffing::class);
    }

    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by_user_id');
    }

    public function isBooked(): bool
    {
        return !is_null($this->booked_by_user_id) || !is_null($this->vatsim_cid);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    public function scopeAvailable($query)
    {
        return $query->whereNull('booked_by_user_id')->whereNull('vatsim_cid');
    }

    public function scopeBooked($query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('booked_by_user_id')
              ->orWhereNotNull('vatsim_cid');
        });
    }
}
