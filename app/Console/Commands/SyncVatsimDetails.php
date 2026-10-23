<?php

namespace App\Console\Commands;

use App\Auth\ControllerRole;
use App\Enums\ControllerRating;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Ratings and subdivisions are otherwise only refreshed at sign-in, so someone who left the
 * subdivision and never signs in again would keep the automatic Controller role. This
 * re-reads their VATSIM member record every day and gives or takes the role to match.
 *
 * A member whose record can't be fetched is left as they are, so an outage, or a sandbox
 * account the live API doesn't know, never takes roles away. Only the automatic Controller role
 * is ever taken away; roles given by hand are left alone. VATSIM_SYNC=false switches it off.
 */
#[Signature('users:sync-vatsim {--pause=1000 : Milliseconds to wait between requests to the VATSIM API}')]
#[Description('Refresh VATSIM ratings and subdivisions, and the automatic Controller role')]
class SyncVatsimDetails extends Command
{
    private const MEMBER_URL = 'https://api.vatsim.net/v2/members/%d';

    public function handle(ControllerRole $controllerRole): void
    {
        if (! config('services.vatsim.sync')) {
            $this->info('Syncing with VATSIM is switched off (VATSIM_SYNC=false); nothing to sync.');

            return;
        }

        if (! $controllerRole->isAutomatic()) {
            $this->info('The automatic Controller role is switched off; nothing to sync.');

            return;
        }

        $pause = max(0, (int) $this->option('pause'));
        $synced = 0;
        $skipped = 0;

        $controllerRole->candidates()->chunkById(100, function ($users) use ($controllerRole, $pause, &$synced, &$skipped) {
            foreach ($users as $user) {
                if ($synced + $skipped > 0) {
                    Sleep::usleep($pause * 1000);
                }

                if ($this->refresh($user)) {
                    $controllerRole->sync($user);
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

        if (! is_array($member) || ! array_key_exists('rating', $member)) {
            $this->warn("{$user->vatsim_cid}: the VATSIM member record had no rating.");

            return false;
        }

        if ($rating = ControllerRating::tryFrom((int) $member['rating'])) {
            $user->rating = $rating;
        }

        // Null when they aren't in a subdivision, as at sign-in.
        $user->subdivision = ($member['subdivision_id'] ?? null) ?: null;
        $user->save();

        return true;
    }
}
