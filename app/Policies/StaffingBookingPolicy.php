<?php

namespace App\Policies;

use App\Models\StaffingBooking;
use App\Models\User;

class StaffingBookingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, StaffingBooking $staffingBooking): bool
    {
        return true;
    }

    // Bookings are managed exclusively through the bot API (VerifyApiKey middleware).
    // Web-authenticated users may not create, modify, or delete bookings.

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, StaffingBooking $staffingBooking): bool
    {
        return false;
    }

    public function delete(User $user, StaffingBooking $staffingBooking): bool
    {
        return false;
    }

    public function restore(User $user, StaffingBooking $staffingBooking): bool
    {
        return false;
    }

    public function forceDelete(User $user, StaffingBooking $staffingBooking): bool
    {
        return false;
    }
}
