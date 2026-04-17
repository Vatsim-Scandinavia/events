<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\EventOccurrence;

class StaffingBooking extends Model
{
    /** @use HasFactory<\Database\Factories\StaffingBookingFactory> */
    use HasFactory;

    protected $table = 'staffing_bookings';
    public $timestamps = true;

    protected $fillable = [
        'position_id',
        'occurrence_id',
        'vatsim_cid',
        'discord_user_id',
        'booked_by_user_id',
        'control_center_booking_id',
    ];

    public function position()
    {
        return $this->belongsTo(StaffingPosition::class, 'position_id');
    }

    public function occurrence()
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
    }

    public function bookedBy()
    {
        return $this->belongsTo(User::class, 'booked_by_user_id');
    }
}
