<?php

namespace App\Console\Commands;

use App\Auth\MemberDetailsSync;
use App\Auth\MemberSyncStopped;
use App\Enums\MemberSyncOutcome;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Member Details are otherwise only refreshed at sign-in, and the Controller Rule is worked out
 * from them, so someone who left the subdivision and never signs in again would stay a Controller.
 * Every day this refreshes every Account's details (see MemberDetailsSync), whether or not a
 * Controller Source is in charge: ratings and names show everywhere.
 *
 * It goes least recently synced first and stops at a rate limit or an outage, so a run cut short
 * picks up where it left off the next day. VATSIM_SYNC=false switches it off, for installs whose
 * people sign in with sandbox accounts the live VATSIM API doesn't know.
 */
#[Signature('users:sync-vatsim {--pause=1000 : Milliseconds to wait between requests to VATSIM}')]
#[Description('Refresh every Account’s Member Details from VATSIM')]
class SyncVatsimDetails extends Command
{
    public function handle(MemberDetailsSync $sync): int
    {
        if (! config('services.vatsim.sync')) {
            $this->info('Syncing with VATSIM is switched off (VATSIM_SYNC=false); nothing to sync.');

            return self::SUCCESS;
        }

        $pause = max(0, (int) $this->option('pause'));
        $counts = array_fill_keys(array_map(fn (MemberSyncOutcome $outcome) => $outcome->value, MemberSyncOutcome::cases()), 0);
        $stopped = null;

        // By id up front, since syncing changes the order this goes in.
        $ids = User::query()
            ->orderByRaw('details_synced_at is not null')
            ->orderBy('details_synced_at')
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $index => $id) {
            $user = User::query()->find($id);

            if ($user === null) {
                continue;
            }

            if ($index > 0) {
                Sleep::usleep($pause * 1000);
            }

            try {
                $outcome = $sync->sync($user);
            } catch (MemberSyncStopped $stop) {
                $stopped = $stop->getMessage();

                break;
            }

            $counts[$outcome->value]++;

            if ($outcome === MemberSyncOutcome::Skipped) {
                $this->warn("{$user->vatsim_cid}: VATSIM had no record to read.");
                Log::info('The VATSIM sync skipped a member it could not read.', ['cid' => $user->vatsim_cid]);
            }
        }

        $summary = "Synced {$counts['via_token']} with their tokens and {$counts['via_public_record']} from the public VATSIM API, skipped {$counts['skipped']}, signed out {$counts['suspended']} suspended.";
        $this->info($summary);
        Log::info('The VATSIM sync ran.', $counts);

        if ($stopped !== null) {
            $this->warn("Stopped early: {$stopped}. The rest go first tomorrow.");
            Log::warning('The VATSIM sync stopped early.', ['reason' => $stopped]);
        }

        return self::SUCCESS;
    }
}
