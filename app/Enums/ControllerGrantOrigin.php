<?php

namespace App\Enums;

/**
 * Who gave a Controller Grant: the Controller Source in charge, or an admin by hand. A source only
 * ever changes the grants a source gave; hand grants are never touched by it.
 */
enum ControllerGrantOrigin: string
{
    case Source = 'source';
    case Hand = 'hand';
}
