<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    use HasFactory;

    public $table = 'areas';

    public function permissions()
    {
        return $this->belongsToMany(Group::class, 'permissions')->withPivot('area_id')->withTimestamps();
    }

    public function staffing() 
    {
        return $this->hasMany(Staffing::class);
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }
}
