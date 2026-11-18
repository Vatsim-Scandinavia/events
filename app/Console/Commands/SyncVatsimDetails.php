<?php

namespace App\Console\Commands;

use App\Auth\Accounts;
use App\Auth\Controllers;
use App\Auth\MemberDetails;
use App\Models\User;
use App\Services\VaccIdentity;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Ratings and subdivisions are otherwise only refreshed at sign-in, and the Controller Rule is
 * worked out from them, so someone who left the subdivision and never signs in again would stay
 * a Controller. This re-reads the VATSIM member record of everyone in the subdivision every day.
 *
 * A member whose record can't be fetched is left as they are, so an outage, or a sandbox
 * account the live API doesn't know, never changes anything. VATSIM_SYNC=false switches it off,
 * and it steps aside while the Controller Rule isn't in charge.
 */
#[Signature('users:sync-vatsim {--pause=1000 : Milliseconds to wait between requests to the VATSIM API}')]
#[Description('Refresh VATSIM ratings and subdivisions, which the Controller Rule follows')]
class SyncVatsimDetails extends Command
{
    private const MEMBER_URL = 'https://api.vatsim.net/v2/members/%d';

    public function handle(Controllers $controllers): void
    {
        if (! config('services.vatsim.sync')) {
            $this->info('Syncing with VATSIM is switched off (VATSIM_SYNC=false); nothing to sync.');

            return;
        }

        if ($controllers->sourceIsInCharge() && $source = $controllers->source()) {
            $this->info("{$source->controllerSourceName()} decides who is a Controller; nothing to sync.");

            return;
        }

        if (! $controllers->ruleIsInCharge()) {
            $this->info('The Controller Rule is switched off; nothing to sync.');

            return;
        }

        $pause = max(0, (int) $this->option('pause'));
        $synced = 0;
        $skipped = 0;

        $subdivision = strtoupper((string) app(VaccIdentity::class)->effective()['subdivision']);

        // Everyone the Controller Rule could make a Controller, or stop being one.
        User::query()->whereRaw('upper(subdivision) = ?', [$subdivision])->chunkById(100, function ($users) use ($pause, &$synced, &$skipped) {
            foreach ($users as $user) {
                if ($synced + $skipped > 0) {
                    Sleep::usleep($pause * 1000);
                }

                if ($this->refresh($user)) {
                    $synced++;
                } else {
                    $skipped++;
                }
            }
        });

        $this->info("Synced {$synced} ".str('member')->plural($synced).", skipped {$skipped}.");
    }

    /**
     * Copy the member's rating and subdivision from VATSIM. False when the record couldn't be read.
     */
    private function refresh(User $user): bool
    {
        try {
            $member = Http::timeout(10)->acceptJson()
                ->get(sprintf(self::MEMBER_URL, $user->vatsim_cid))
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException $exception) {
            $this->warn("{$user->vatsim_cid}: could not load the VATSIM member record ({$exception->getMessage()}).");

            return false;
        }

        $details = is_array($member) ? MemberDetails::fromPublicRecord($user->vatsim_cid, $member) : null;

        if ($details === null) {
            $this->warn("{$user->vatsim_cid}: the VATSIM member record had no rating.");

            return false;
        }

        app(Accounts::class)->apply($details);

        return true;
    }
}
