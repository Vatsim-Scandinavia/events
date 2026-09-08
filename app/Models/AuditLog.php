<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $actor_cid
 * @property string|null $actor_name
 * @property string $subject_type
 * @property int $subject_id
 * @property string $subject_label
 * @property string $event
 * @property string $source
 * @property array<string, mixed> $old_values
 * @property array<string, mixed> $new_values
 * @property Carbon $created_at
 */
#[Fillable(['actor_cid', 'actor_name', 'subject_type', 'subject_id', 'subject_label', 'event', 'source', 'old_values', 'new_values', 'created_at'])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'actor_cid' => 'integer',
            'subject_id' => 'integer',
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
