<?php

namespace App\Enums;

/**
 * Who gave someone a role: an admin, or the hub itself (the automatic Controller role).
 * The hub only ever takes away roles it gave.
 */
enum AssignmentSource: string
{
    case Manual = 'manual';
    case Automatic = 'automatic';
}
