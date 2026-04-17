<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\EventOccurrence;

class StaffingPosition extends Model
{
    /** @use HasFactory<\Database\Factories\StaffingPositionFactory> */
    use HasFactory;

    public $timestamps = true;
    protected $table = 'staffing_positions';

    protected $fillable = [
        'section_id',
        'position_id',
        'position_name',
        'start_time',
        'end_time',
        'order',
        'is_local_booking',
    ];

    public function section()
    {
        return $this->belongsTo(StaffingSection::class, 'section_id');
    }

    public function bookings()
    {
        return $this->hasMany(StaffingBooking::class, 'position_id');
    }

    public function bookingForOccurrence(EventOccurrence $occurrence)
    {
        return $this->bookings()->where('occurrence_id', $occurrence->id)->first();
    }

    public function isBooked(?EventOccurrence $occurrence = null): bool
    {
        // Use the already-loaded relation to avoid an extra query when bookings
        // have been eager-loaded (e.g. with(['bookings' => ...])).
        if ($this->relationLoaded('bookings')) {
            $bookings = $this->bookings;

            if ($occurrence) {
                return $bookings->contains('occurrence_id', $occurrence->id);
            }

            return $bookings->isNotEmpty();
        }

        if ($occurrence) {
            return $this->bookings()->where('occurrence_id', $occurrence->id)->exists();
        }

        return $this->bookings()->exists();
    }
}
