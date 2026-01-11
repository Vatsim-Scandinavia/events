<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'calendar_id',
        'title',
        'short_description',
        'long_description',
        'recurrence_interval',
        'recurrence_unit',
        'recurrence_end_date',
        'published',
        'image',
        'user_id',
    ];

    public function instances()
    {
        return $this->hasMany(EventInstance::class);
    }

    public function nextInstance()
    {
        return $this->hasOne(EventInstance::class)
            ->ofMany([
                'start_time' => 'min',
            ], function ($query) {
                $query->where('end_time', '>=', now());
            });
    }

    public function scopeUpcoming($query)
    {
        return $query->whereHas('instances', fn($q) => $q->where('end_time', '>=', now()));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function calendar()
    {
        return $this->belongsTo(Calendar::class);
    }

    public function staffing()
    {
        return $this->hasOneThrough(
            Staffing::class,
            EventInstance::class,
            'event_id',
            'event_instance_id',
            'id',
            'id'
        );
    }

    /**
     * Get the staffing record via the event instances.
     */
    public function instanceStaffings()
    {
        return $this->hasManyThrough(
            Staffing::class,
            EventInstance::class,
            'event_id',
            'event_instance_id',
            'id',
            'id'
        );
    }

    public function generateRecurrences()
    {
        $recurrences = [];

        if ($this->recurrence_interval && $this->recurrence_unit) {
            $start = Carbon::parse($this->start_date);
            $end = Carbon::parse($this->end_date);

            $interval = (int) ($this->recurrence_interval ?? 1);
            $unit = $this->recurrence_unit ?? 'day';

            $maxRecurrenceEnd = $start->copy()->addMonths(6)->endOfDay();

            $recurrenceEnd = $this->recurrence_end_date ? Carbon::parse($this->recurrence_end_date)->endOfDay() : Carbon::now()->addYear()->endOfDay();
            if ($recurrenceEnd > $maxRecurrenceEnd) {
                $recurrenceEnd = $maxRecurrenceEnd;
            }

            switch ($unit) {
                case 'day':
                    $intervalType = 'days';
                    break;
                case 'week':
                    $intervalType = 'weeks';
                    break;
                case 'month':
                    $intervalType = 'months';
                    break;
                case 'year':
                    $intervalType = 'years';
                    break;
                default:
                    $intervalType = 'days';
                    break;
            }

            $start = $start->copy()->add($interval, $intervalType);
            $end = $end->copy()->add($interval, $intervalType);

            while ($start <= $recurrenceEnd) {
                if ($start > $end) {
                    break;
                }

                $recurrences[] = new Event([
                    'title' => $this->title,
                    'short_description' => $this->short_description,
                    'long_description' => $this->long_description,
                    'start_date' => $start,
                    'end_date' => $end,
                    'calendar_id' => $this->calendar_id,
                    'parent_id' => $this->id,
                    'recurrence_interval' => $this->recurrence_interval,
                    'recurrence_unit' => $this->recurrence_unit,
                    'recurrence_end_date' => $this->recurrence_end_date,
                    'image' => $this->image,
                    'user_id' => $this->user_id,
                ]);

                $start = $start->copy()->add($interval, $intervalType);
                $end = $end->copy()->add($interval, $intervalType);
            }
        } else {
            $recurrences[] = $this;
        }

        return $recurrences;
    }

    public function discordMessage()
    {
        return $this->hasOne(DiscordMessage::class);
    }
}
