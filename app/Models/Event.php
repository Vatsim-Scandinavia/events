<?php

namespace App\Models;

use App\PermissionName;
use Carbon\CarbonImmutable;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $owner_team_id
 * @property string $title
 * @property string $short_description
 * @property string $description
 * @property string $timezone
 * @property string $local_start
 * @property string $local_end
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property string $recurrence
 * @property int $recurrence_interval
 * @property int|null $monthly_week
 * @property CarbonImmutable|null $recurrence_until
 * @property string $status
 * @property string|null $banner_path
 * @property string|null $cancellation_reason
 * @property CarbonImmutable|null $cancelled_at
 * @property Team $owner
 * @property Collection<int, Airport> $airports
 * @property Collection<int, EventCancellation> $cancellations
 * @property Collection<int, EventCollaboration> $collaborations
 * @property Collection<int, EventRoster> $rosters
 */
#[Fillable(['owner_team_id', 'title', 'short_description', 'description', 'timezone', 'local_start', 'local_end', 'starts_at', 'ends_at', 'recurrence', 'recurrence_interval', 'monthly_week', 'recurrence_until', 'status', 'banner_path', 'cancellation_reason', 'cancelled_at'])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    /** @return BelongsTo<Team, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'owner_team_id');
    }

    /** @return BelongsToMany<Airport, $this> */
    public function airports(): BelongsToMany
    {
        return $this->belongsToMany(Airport::class)->orderBy('icao');
    }

    /** @return HasMany<EventCancellation, $this> */
    public function cancellations(): HasMany
    {
        return $this->hasMany(EventCancellation::class);
    }

    /** @return HasMany<EventCollaboration, $this> */
    public function collaborations(): HasMany
    {
        return $this->hasMany(EventCollaboration::class);
    }

    /** @return HasMany<EventRoster, $this> */
    public function rosters(): HasMany
    {
        return $this->hasMany(EventRoster::class);
    }

    /** @param Builder<Event> $query */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->isAdministrator()) {
            return;
        }

        $teamIds = $user->teamsWithPermission(PermissionName::ViewEvents)->select('teams.id');
        $query->where(fn (Builder $query) => $query->whereIn('owner_team_id', $teamIds)
            ->orWhereHas('collaborations', fn (Builder $query) => $query
                ->whereNotNull('accepted_at')->whereIn('team_id', $teamIds)));
    }

    /** @return array<string, mixed> */
    public function auditValues(): array
    {
        return [
            ...$this->only(['owner_team_id', 'title', 'short_description', 'description', 'timezone', 'local_start', 'local_end', 'recurrence', 'recurrence_interval', 'monthly_week', 'status', 'banner_path', 'cancellation_reason']),
            'recurrence_until' => $this->recurrence_until?->toDateString(),
            'airports' => $this->airports()->pluck('icao')->all(),
            'cancellations' => $this->cancellations()->orderBy('occurrence_date')->get(['occurrence_date', 'reason'])->toArray(),
            'collaborations' => $this->collaborations()->orderBy('team_id')->get(['team_id', 'accepted_at'])->toArray(),
        ];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime',
            'recurrence_until' => 'immutable_date', 'cancelled_at' => 'immutable_datetime',
            'recurrence_interval' => 'integer', 'monthly_week' => 'integer',
        ];
    }
}
