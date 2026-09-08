<?php

namespace App;

enum RoleName: string
{
    case Administrator = 'Administrator';
    case EventCoordinator = 'Event Coordinator';
    case VaccStaff = 'vACC Staff';
    case Controller = 'Controller';
    case Pilot = 'Pilot';

    public function isGlobal(): bool
    {
        return $this === self::Administrator || $this === self::Pilot;
    }
}
