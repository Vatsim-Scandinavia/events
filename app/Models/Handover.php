<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Handover extends Model
{
    use HasFactory;

    public $table = 'users';

    public $timestamps = false;

    /**
     * Initialises the Handover model with a custom, yet dynamic, connection.
     * The custom connection is a prerequisite for the current coupling between
     * Control Center and Handover.
     */
    public function __construct(array $attributes = array())
    {
        parent::__construct($attributes);
        $this->setConnection(config('database.handover'));
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id', 'id');
    }

    
}
