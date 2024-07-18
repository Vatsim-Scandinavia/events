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
        'id',
        'calendar_id',
        'title',
        'description',
        'start_date',
        'end_date',
        'is_full_day',
        'recurrence_interval',
        'recurrence_unit',
        'recurrence_end_date',
        'published',
        'image',
        'user_id',
        'area_id'
    ];

    protected $dates = [
        'start_date',
        'end_date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function calendar() 
    {
        return $this->belongsTo(Calendar::class);
    }

    public function children() 
    {
        return $this->hasMany(Event::class, 'parent_id');
    }

    public function parent() 
    {
        return $this->belongsTo(Event::class, 'parent_id');
    }

    public function generateRecurrences()
    {
        $recurrences = [];

        // Check if recurrence_interval and recurrence_unit are set
        if ($this->recurrence_interval && $this->recurrence_unit) {
            $start = Carbon::parse($this->start_date); // Ensure $start is a Carbon instance
            $end = Carbon::parse($this->end_date); // Ensure $end is a Carbon instance

            $interval = $this->recurrence_interval ?? 1;
            $unit = $this->recurrence_unit ?? 'day';

            $recurrenceEnd = $this->recurrence_end_date ? Carbon::parse($this->recurrence_end_date)->endOfDay() : Carbon::now()->addYear()->endOfDay();

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
                    $intervalType = 'days'; // Default to daily if no valid unit provided
                    break;
            }

            // Calculate the first recurrence start date
            $start = $start->copy()->add($interval, $intervalType);
            $end = $end->copy()->add($interval, $intervalType);

            // Start generating recurrences
            while ($start <= $recurrenceEnd) {
                // Check if the current start date is after the original end date (for handling end dates in the past)
                if ($start > $end) {
                    break;
                }

                $recurrences[] = new Event([
                    'title' => $this->title,
                    'description' => $this->description,
                    'start_date' => $start,
                    'end_date' => $end,
                    'is_full_day' => $this->is_full_day,
                    'calendar_id' => $this->calendar_id,
                    'parent_id' => $this->id,
                    'recurrence_interval' => $this->recurrence_interval,
                    'recurrence_unit' => $this->recurrence_unit,
                    'recurrence_end_date' => $this->recurrence_end_date,
                    'image' => $this->image,
                    'user_id' => $this->user_id,
                    'area_id' => $this->area_id,
                ]);

                // Move to the next recurrence date
                $start = $start->copy()->add($interval, $intervalType);
                $end = $end->copy()->add($interval, $intervalType);
            }
        } else {
            // If no recurrence interval and unit are set, just return the original event itself as a single instance
            $recurrences[] = $this;
        }

        return $recurrences;
    }

    public function checkRecurrences() 
    {
        $expectedRecurrences = [];
        $missingRecurrences = [];
        $extraRecurrences = [];
        $actualRecurrences = $this->children()->orderBy('start_date')->get();

        if($this->recurrence_interval && $this->recurrence_unit) {
            $start = Carbon::parse($this->start_date);
            $end = Carbon::parse($this->end_date);

            $interval = $this->recurrence_interval ?? 1;
            $unit = $this->recurrence_unit ?? 'day';

            $recurrenceEnd = $this->recurrence_end_date ? Carbon::parse($this->recurrence_end_date)->endOfDay() : Carbon::now()->addYear()->endOfDay();

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
                if($start > $end) {
                    break;
                }

                $expectedRecurrences[] = [
                    'start_date' => $start->copy(),
                    'end_date' => $end->copy(),
                ];

                $start = $start->copy()->add($interval, $intervalType);
                $end = $end->copy()->add($interval, $intervalType);
            }

            foreach ($expectedRecurrences as $expected) {
                $matchFound = false;

                foreach ($actualRecurrences as $key => $actual) {
                    if($expected['start_date']->equalTo(Carbon::parse($actual->start_date)) && $expected['end_date']->equalTo(Carbon::parse($actual->end_date))) {
                        $matchFound = true;
                        unset($actualRecurrences[$key]);
                        break;
                    }
                }

                if(!$matchFound) {
                    $missingRecurrences[] = $expected;
                }
            }

            $extraRecurrences = $actualRecurrences;

            return [
                'missing_recurrences' => $missingRecurrences,
                'extra_recurrences' => $extraRecurrences
            ];
        }
    }
}
