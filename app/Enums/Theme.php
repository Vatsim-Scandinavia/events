<?php

namespace App\Enums;

/**
 * The colors the whole Hub is shown in, chosen by an Administrator.
 */
enum Theme: string
{
    /** The neutral look the Hub ships with. */
    case Default = 'default';

    /** The vACC's own primary and accent colors, set on the settings page. */
    case Brand = 'brand';
}
