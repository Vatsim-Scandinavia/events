<?php

namespace App\Console\Commands;

use App\Actions\Authorization\UpdateRoleAssignments;
use App\Models\User;
use App\RoleName;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('authorization:grant-administrator {cid : The existing VATSIM CID to grant Administrator}')]
#[Description('Grant global Administrator access to an existing account from the trusted server console')]
class GrantAdministrator extends Command
{
    public function handle(UpdateRoleAssignments $assignments): int
    {
        $user = User::find($this->argument('cid'));

        if ($user === null) {
            $this->error('That CID must sign in before it can be granted Administrator.');

            return self::FAILURE;
        }

        $assignments->grant($user, RoleName::Administrator);
        $this->info('Global Administrator access granted to CID '.$user->cid.'.');

        return self::SUCCESS;
    }
}
