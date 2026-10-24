<?php

namespace App\Auth;

use App\Models\User;

/**
 * Something other than the hub's own rule that decides who automatically holds the Controller
 * role, and where: an Integration feature such as a training system's roster sync. Only one is
 * in charge at a time; while one is, the hub's S1-and-subdivision rule steps aside.
 *
 * Every Controller role given automatically (AssignmentSource::Automatic) is the source's to give
 * and take away, including ones an earlier source gave. Manual ones are never its to touch.
 */
interface ControllerSource
{
    /**
     * What admins see as handing out the Controller role, e.g. "Control Center roster sync".
     */
    public function controllerSourceName(): string;

    /**
     * Give or take away the user's automatic Controller roles to match the source, at sign-in.
     */
    public function syncController(User $user): void;
}
