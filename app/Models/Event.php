<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'events';
    public $timestamps = true;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

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

    public function futureOccurrences()
    {
        return $this->hasMany(EventOccurrence::class)->future();
    }

    public function staffing()
    {
        return $this->hasOne(Staffing::class);
    }

    public function isRecurring(): bool
    {
        return !empty($this->recurrence_rule);
    }
}
