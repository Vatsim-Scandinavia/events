<?php

namespace App\Enums;

/**
 * How one run of an Integration feature's sync went.
 */
enum IntegrationRunStatus: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    /** Nothing was changed: the external system couldn't be read, or the sync broke. */
    case Failed = 'failed';
    /** Nothing was changed, as the changes looked suspicious; waiting for an Administrator. */
    case Held = 'held';
    /** A held run an Administrator let through, by running the sync again. */
    case Approved = 'approved';
}
