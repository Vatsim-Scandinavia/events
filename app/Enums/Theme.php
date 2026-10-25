<?php

namespace App\Enums;

/**
 * The colors the whole Hub is shown in, chosen by an Administrator.
 * Each theme's colors are set in resources/css/app.css under its data-theme value.
 */
enum Theme: string
{
    /** The neutral look the Hub ships with. */
    case Default = 'default';

    /** The dark greys and steel blue of VATSIM Scandinavia's Control Center. */
    case Vatsca = 'vatsca';

    public function label(): string
    {
        return match ($this) {
            self::Default => 'Default',
            self::Vatsca => 'VATSIM Scandinavia',
        };
    }
}
