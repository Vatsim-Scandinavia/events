<?php

namespace App\Enums;

/**
 * Everything a role can be allowed to do. Each case is also a gate of the same name.
 */
enum Permission: string
{
    /** Create, edit and delete events. */
    case ManageEvents = 'manage-events';

    /** Set up positions and shifts, see everyone's interest and publish rosters. */
    case ManageStaffing = 'manage-staffing';

    /** See every event and its staffing as staff do, without changing anything. */
    case ViewEvents = 'view-events';

    /** Book shifts, show interest in staffing and be put on rosters. */
    case BookPositions = 'book-positions';

    /** See every user and assign their roles. */
    case ManageUsers = 'manage-users';

    public function label(): string
    {
        return match ($this) {
            self::ManageEvents => 'Manage events',
            self::ManageStaffing => 'Manage staffing',
            self::ViewEvents => 'View all events',
            self::BookPositions => 'Control events',
            self::ManageUsers => 'Manage users',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ManageEvents => 'Create, edit and delete events and their dates.',
            self::ManageStaffing => 'Set up positions and shifts, see everyone’s interest and publish rosters.',
            self::ViewEvents => 'See every event and its staffing as staff do, without changing anything.',
            self::BookPositions => 'Book shifts, show interest in staffing and be put on rosters.',
            self::ManageUsers => 'See every user and assign their roles.',
        };
    }
}
