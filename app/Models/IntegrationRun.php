<?php

namespace App\Models;

use App\Enums\IntegrationRunStatus;
use Database\Factories\IntegrationRunFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One run of an Integration feature's sync: when it ran, how it went and what it changed.
 * Kept for 90 days, so admins can tell whether an Integration is healthy.
 */
class IntegrationRun extends Model
{
    /** @use HasFactory<IntegrationRunFactory> */
    use HasFactory, MassPrunable;

    public const int KEPT_FOR_DAYS = 90;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['integration', 'feature', 'status', 'added', 'updated', 'removed', 'message', 'warnings', 'approved_by', 'started_at', 'finished_at'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'added' => 0,
        'updated' => 0,
        'removed' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => IntegrationRunStatus::class,
            'warnings' => 'array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    /**
     * The Administrator who let a held run through, on the run that did it.
     *
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('started_at', '<', now()->subDays(self::KEPT_FOR_DAYS));
    }
}
