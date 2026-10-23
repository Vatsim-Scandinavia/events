<?php

namespace App\Console\Commands;

use App\Auth\Roles;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Keeps the built-in roles in place. Runs on every deploy, so a new permission reaches
 * Administrator without a migration.
 */
#[Signature('roles:sync')]
#[Description('Create the built-in roles and give Administrator every permission')]
class SyncRoles extends Command
{
    public function handle(): int
    {
        Roles::sync();
        $this->info('Built-in roles are in place.');

        return self::SUCCESS;
    }
}
