<?php

namespace App\Helpers;

enum EventHelper: string 
{
    case NONE = '0';
    case DAY = 'day';
    case WEEK = 'week';
    case MONTH = 'month';
    case YEAR = 'year';

    public static function labels() : array 
    {
        return [
            self::NONE->value => 'None',
            self::DAY->value => 'Daily',
            self::WEEK->value => 'Weekly',
            self::MONTH->value => 'Monthly',
            self::YEAR->value => 'Yearly',
        ];
    }
}