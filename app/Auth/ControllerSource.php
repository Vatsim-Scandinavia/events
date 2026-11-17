<?php

namespace App\Auth;

use App\Models\User;

/**
 * An Integration feature that decides who is a Controller instead of the Controller Rule, such as
 * a training system's roster sync. Only one is in charge at a time; while one is, the rule steps
 * aside.
 *
 * It records its decisions as Controller Grants through App\Auth\Controllers (planSourceGrants
 * and applySourceGrants), and owns every grant a source gave, including an earlier one's. Hand
 * grants are never its to touch.
 */
interface ControllerSource
{
    /**
     * What admins see as deciding who is a Controller, e.g. "Control Center’s roster sync".
     */
    public function controllerSourceName(): string;

    /**
     * Whether it has decided anything yet, e.g. synced at least once. Until it has, the Controller
     * Rule keeps deciding, so switching a source on never stops anyone being a Controller before
     * the source knows who should be.
     */
    public function hasDecided(): bool;

    /**
     * Bring the user's source grants up to date, as they sign in.
     */
    public function syncController(User $user): void;
}
