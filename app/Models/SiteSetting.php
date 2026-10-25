<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One installation-wide setting chosen in the app, such as the vACC's name or logo.
 * Read them through App\Settings\VaccIdentity, which also applies the env values.
 */
class SiteSetting extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = ['key', 'value'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }
}
