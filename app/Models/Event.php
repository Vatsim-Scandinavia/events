<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'calendar_id',
        'title',
        'short_description',
        'long_description',
        'staffing_description',
        'featured_airports',
        'banner_path',
        'start_datetime',
        'end_datetime',
        'recurrence_rule',
        'recurrence_parent_id',
        'discord_staffing_message_id',
        'discord_staffing_channel_id',
        'notified_occurrences',
        'cancelled_occurrences',
        'created_by',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'featured_airports' => 'array',
        'notified_occurrences' => 'array',
        'cancelled_occurrences' => 'array',
    ];

    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param  \DateTimeInterface  $date
     * @return string
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d\TH:i:sP');
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recurrenceParent(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'recurrence_parent_id');
    }

    public function recurrenceInstances(): HasMany
    {
        return $this->hasMany(Event::class, 'recurrence_parent_id');
    }

    public function staffings(): HasMany
    {
        return $this->hasMany(Staffing::class);
    }

    public function isRecurring(): bool
    {
        return !empty($this->recurrence_rule);
    }

    public function isRecurrenceInstance(): bool
    {
        return !empty($this->recurrence_parent_id);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_datetime', '>=', now());
    }

    public function scopePast($query)
    {
        return $query->where('end_datetime', '<', now());
    }

    public function scopeInCalendar($query, $calendarId)
    {
        return $query->where('calendar_id', $calendarId);
    }

    /**
     * Check if a specific occurrence is cancelled
     */
    public function isOccurrenceCancelled(string $occurrenceDate): bool
    {
        $cancelled = $this->cancelled_occurrences ?? [];
        return in_array($occurrenceDate, $cancelled);
    }

    /**
     * Cancel a specific occurrence
     */
    public function cancelOccurrence(string $occurrenceDate): void
    {
        $cancelled = $this->cancelled_occurrences ?? [];
        if (!in_array($occurrenceDate, $cancelled)) {
            $cancelled[] = $occurrenceDate;
            $this->cancelled_occurrences = $cancelled;
            $this->save();
        }
    }

    /**
     * Uncancel a specific occurrence
     */
    public function uncancelOccurrence(string $occurrenceDate): void
    {
        $cancelled = $this->cancelled_occurrences ?? [];
        $key = array_search($occurrenceDate, $cancelled);
        if ($key !== false) {
            unset($cancelled[$key]);
            $this->cancelled_occurrences = array_values($cancelled);
            $this->save();
        }
    }
}
