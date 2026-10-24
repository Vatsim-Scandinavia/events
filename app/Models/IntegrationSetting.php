<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * How admins set up one Integration: whether it's on, which of its features are, and its
 * settings. Read them through App\Integrations\Integrations, which also applies the env values.
 */
class IntegrationSetting extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = ['key', 'enabled', 'features', 'settings', 'removal_limit'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'enabled' => false,
        'removal_limit' => 25,
    ];

    /**
     * Settings hold API tokens, so they're encrypted at rest.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'features' => 'array',
            'settings' => 'encrypted:array',
            'removal_limit' => 'integer',
        ];
    }
}
