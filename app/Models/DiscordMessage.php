<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscordMessage extends Model
{
    use HasFactory;

    public $timestamps = false;

    public $incrementing = false;

    public $primaryKey = 'message_id';

    protected $fillable = [
        'message_id',
        'expires_at',
        'event_id',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
