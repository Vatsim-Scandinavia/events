<?php

namespace App\Enums;

/**
 * VATSIM controller ratings, by their numeric id in the VATSIM API.
 */
enum ControllerRating: int
{
    case Suspended = -1;
    case Inactive = 0;
    case OBS = 1;
    case S1 = 2;
    case S2 = 3;
    case S3 = 4;
    case C1 = 5;
    case C2 = 6;
    case C3 = 7;
    case I1 = 8;
    case I2 = 9;
    case I3 = 10;
    case SUP = 11;
    case ADM = 12;

    public function label(): string
    {
        return match ($this) {
            self::Suspended => 'SUS',
            self::Inactive => 'INA',
            default => $this->name,
        };
    }
}
