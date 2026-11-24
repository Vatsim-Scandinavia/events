<?php

namespace App\Console\Commands;

use App\Jobs\SyncMemberDetails;
use App\Models\SignInToken;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Member Details are otherwise only refreshed at sign-in, so every day this queues a sync for
 * each Account with tokens from its Sign-in Provider (see SyncMemberDetails), whether or not a
 * Controller Source is in charge: names, emails and ratings show everywhere. Accounts without
 * tokens that work wait for their next sign-in.
 */
#[Signature('users:sync-vatsim')]
#[Description('Queue a refresh of every Account’s Member Details from its Sign-in Provider')]
class SyncVatsimDetails extends Command
{
    public function handle(): int
    {
        $userIds = SignInToken::query()->orderBy('user_id')->pluck('user_id');

        $userIds->each(fn (int $userId) => SyncMemberDetails::dispatch($userId));

        $this->info("Queued a sync for {$userIds->count()} accounts.");

        return self::SUCCESS;
    }
}
