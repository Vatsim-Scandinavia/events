<?php

namespace App\Models;

use Database\Factories\AirportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $icao
 * @property string $name
 * @property string $country
 */
#[Fillable(['icao', 'name', 'country'])]
class Airport extends Model
{
    /** @use HasFactory<AirportFactory> */
    use HasFactory;
}
