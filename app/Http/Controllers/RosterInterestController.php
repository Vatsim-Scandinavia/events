<?php

namespace App\Http\Controllers;

use App\Actions\RecordAudit;
use App\Actions\RosterMutation;
use App\Http\Requests\RosterInterestRequest;
use App\Models\EventRoster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RosterInterestController extends Controller
{
    public function update(RosterInterestRequest $request, EventRoster $roster, RosterMutation $mutation, RecordAudit $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $roster, $mutation, $audit): void {
            $roster = $mutation->lockRoster($roster);
            Gate::authorize('participate', $roster);
            if ($roster->mode !== 'open_interest') {
                throw ValidationException::withMessages(['interest' => 'This roster does not accept open interest.']);
            }
            $data = $request->validated();
            $positionIds = array_map(intval(...), $data['position_ids']);
            sort($positionIds);
            if ($roster->positions()->whereIn('id', $positionIds)->count() !== count($positionIds)) {
                throw ValidationException::withMessages(['position_ids' => 'Select positions from this roster.']);
            }
            $occurrence = $mutation->occurrence($roster->event, $roster->occurrence_date);
            $ranges = [];
            foreach ($data['availability'] as $index => $range) {
                $mutation->validateRange($range['starts_at'], $range['ends_at'], $occurrence, "availability.$index.starts_at");
                foreach ($ranges as $previous) {
                    if ($range['starts_at'] < $previous['ends_at'] && $range['ends_at'] > $previous['starts_at']) {
                        throw ValidationException::withMessages(["availability.$index.starts_at" => 'Availability ranges must not overlap.']);
                    }
                }
                $ranges[] = $range;
            }
            usort($ranges, fn (array $left, array $right): int => $left['starts_at'] <=> $right['starts_at']);
            $before = $roster->auditValues();
            $roster->interests()->updateOrCreate(['user_cid' => $request->user()->cid], ['position_ids' => $positionIds, 'availability' => $ranges]);
            $audit->handle($roster, 'interest_submitted', $before, $roster->auditValues());
        });

        return to_route('events.roster.show', ['event' => $roster->event_id, 'date' => $roster->occurrence_date]);
    }

    public function destroy(Request $request, EventRoster $roster, RosterMutation $mutation, RecordAudit $audit): RedirectResponse
    {
        Gate::authorize('view', $roster);
        DB::transaction(function () use ($request, $roster, $mutation, $audit): void {
            $roster = $mutation->lockRoster($roster);
            Gate::authorize('view', $roster);
            $before = $roster->auditValues();
            $roster->interests()->where('user_cid', $request->user()->cid)->delete();
            $audit->handle($roster, 'interest_withdrawn', $before, $roster->auditValues());
        });

        return to_route('events.roster.show', ['event' => $roster->event_id, 'date' => $roster->occurrence_date]);
    }
}
