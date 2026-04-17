<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffingSection extends Model
{
    /** @use HasFactory<\Database\Factories\StaffingSectionFactory> */
    use HasFactory;

    public $timestamps = true;
    protected $table = 'staffing_sections';

    protected $fillable = [
        'title',
        'staffing_id',
        'order',
    ];

    public function staffing()
    {
        return $this->belongsTo(Staffing::class);
    }

    public function positions()
    {
        return $this->hasMany(StaffingPosition::class, 'section_id')->orderBy('order');
    }
}
