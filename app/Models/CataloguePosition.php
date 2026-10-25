<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A known controller position in the Hub-wide Position catalogue, which staff pick from when
 * adding positions to an Event. An Integration fills it and owns its entries, so they're
 * read-only in the Hub. Event Positions copy what they need, so changes here never alter them.
 */
class CataloguePosition extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['callsign', 'name', 'frequency', 'integration'];
}
