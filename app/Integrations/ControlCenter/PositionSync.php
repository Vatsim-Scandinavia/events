<?php

namespace App\Integrations\ControlCenter;

use App\Integrations\Feature;
use App\Integrations\Syncable;
use App\Integrations\SyncContext;
use App\Integrations\SyncResult;
use App\Models\CataloguePosition;
use Illuminate\Console\Scheduling\Event;

/**
 * Fills the Position catalogue from Control Center's positions, once a day. Switched off, it
 * takes its positions out of the catalogue again; Events keep theirs.
 */
class PositionSync extends Feature implements Syncable
{
    /** As the staffing planner accepts them. */
    private const string CALLSIGN = '/^[A-Z0-9]+(_[A-Z0-9]+)+$/';

    private const string FREQUENCY = '/^1[1-3]\d\.\d{2,3}$/';

    public function key(): string
    {
        return 'positions';
    }

    public function name(): string
    {
        return 'Position sync';
    }

    public function description(): string
    {
        return 'Suggests Control Center’s positions, with their frequencies, when staff add positions to an event. Syncs every day.';
    }

    public function schedule(Event $event): void
    {
        $event->daily();
    }

    public function sync(SyncContext $context): SyncResult
    {
        $listed = [];
        $skipped = [];

        foreach ((new ControlCenterApi($context->configuration))->positions() as $position) {
            $callsign = strtoupper($position['callsign']);

            if (strlen($callsign) > 20 || ! preg_match(self::CALLSIGN, $callsign)) {
                $skipped[] = $position['callsign'];

                continue;
            }

            $listed[$callsign] = [
                'name' => $position['name'],
                'frequency' => $position['frequency'] !== null && preg_match(self::FREQUENCY, $position['frequency']) ? $position['frequency'] : null,
            ];
        }

        $existing = CataloguePosition::query()->where('integration', $this->integrationKey())->get()->keyBy('callsign');
        $removing = $existing->keys()->diff(array_keys($listed));
        $warnings = $skipped === [] ? [] : ['Skipped positions that aren’t callsigns: '.implode(', ', $skipped).'.'];

        if (! $context->mayRemove($removing->count(), $existing->count())) {
            return SyncResult::held(
                "It would remove {$removing->count()} of {$existing->count()} positions. Check Control Center, or approve to sync anyway.",
                $warnings,
            );
        }

        CataloguePosition::query()->where('integration', $this->integrationKey())->whereIn('callsign', $removing)->delete();

        $added = 0;
        $updated = 0;

        foreach ($listed as $callsign => $details) {
            $position = $existing->get($callsign) ?? new CataloguePosition(['callsign' => $callsign, 'integration' => $this->integrationKey()]);
            $position->fill($details);

            if (! $position->exists) {
                // Taken over from another Integration, if one had it.
                CataloguePosition::query()->where('callsign', $callsign)->delete();
                $added++;
            } elseif ($position->isDirty()) {
                $updated++;
            }

            $position->save();
        }

        return SyncResult::succeeded($added, $updated, $removing->count(), $warnings);
    }

    public function switchedOff(): void
    {
        CataloguePosition::query()->where('integration', $this->integrationKey())->delete();
    }

    private function integrationKey(): string
    {
        return app(ControlCenter::class)->key();
    }
}
