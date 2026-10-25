<?php

namespace App\Console\Commands;

use App\Auth\Roles;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Hands out the first Admin role, before anyone can do it from the admin pages.
 */
#[Signature('users:make-admin {cid : The VATSIM CID of someone who has signed in before}')]
#[Description('Give a user the Admin role')]
class MakeAdmin extends Command
{
    public function handle(): int
    {
        $user = User::query()->where('vatsim_cid', $this->argument('cid'))->first();

        if (! $user) {
            $this->error('Nobody with that CID has signed in yet.');

            return self::FAILURE;
        }

        $user->assignRole(Roles::ADMINISTRATOR);
        $this->info("{$user->name} is now an Administrator.");

        return self::SUCCESS;
    }
}
