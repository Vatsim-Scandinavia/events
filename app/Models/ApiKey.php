<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'read_only',
        'last_used_at',
    ];

    protected $casts = [
        'read_only' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function canWrite(): bool
    {
        return !$this->read_only;
    }

    public function recordUsage(): void
    {
        $this->update(['last_used_at' => now()]);
    }
}
