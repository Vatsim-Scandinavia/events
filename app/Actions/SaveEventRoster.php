<?php

namespace App\Actions;

use App\Http\Requests\EventRosterRequest;
use App\Models\Event;
use App\Models\EventRoster;
use App\Models\RosterPosition;
use App\Models\RosterShift;
use App\Models\RosterSlot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveEventRoster
{
    public function __construct(private RosterMutation $mutation, private RecordAudit $audit) {}

    public function handle(EventRosterRequest $request, Event $event, string $date): EventRoster
    {
        return DB::transaction(function () use ($request, $event, $date): EventRoster {
            $event = $this->mutation->lockEvent($event->id);
            Gate::authorize('update', $event);
            $occurrence = $this->mutation->occurrence($event, $date);
            $data = $request->validated();
            $roster = EventRoster::where('event_id', $event->id)->where('occurrence_date', $date)->first()
                ?? new EventRoster(['event_id' => $event->id, 'occurrence_date' => $date]);
            $before = $roster->exists ? $roster->auditValues() : [];
            $existingSlots = $roster->exists ? $roster->slots()->get()->keyBy('id') : collect();
            $existingShifts = $roster->exists ? $roster->shifts()->get()->keyBy('id') : collect();
            $existingPositions = $roster->exists ? $roster->positions()->get()->keyBy('id') : collect();
            $interestedPositionIds = $roster->exists ? $roster->interests()->get()->flatMap->position_ids->unique()->all() : [];
            $hasBookings = $existingSlots->contains(fn (RosterSlot $slot): bool => $slot->booked_by !== null);
            if ($roster->exists && $data['mode'] !== $roster->mode && ($hasBookings || $interestedPositionIds !== [])) {
                throw ValidationException::withMessages(['mode' => 'Withdraw existing bookings or interest before changing the roster type.']);
            }
            if ($data['mode'] === 'pre_slotted' && $data['positions'] !== []) {
                throw ValidationException::withMessages(['positions' => 'Pre-slotted rosters use shifts and slots.']);
            }
            if ($data['mode'] === 'open_interest' && $data['shifts'] !== []) {
                throw ValidationException::withMessages(['shifts' => 'Open interest rosters use a list of positions.']);
            }

            $slotIds = [];
            $shiftIds = [];
            $ranges = [];
            foreach ($data['shifts'] as $shiftIndex => $shiftData) {
                $shiftId = $shiftData['id'] ?? null;
                if ($shiftId !== null && ! $existingShifts->has($shiftId)) {
                    throw ValidationException::withMessages(["shifts.$shiftIndex.id" => 'This shift does not belong to this roster.']);
                }
                if ($shiftId !== null) {
                    $shiftIds[] = $shiftId;
                }
                $callsigns = [];
                foreach ($shiftData['slots'] as $slotIndex => $slotData) {
                    $field = "shifts.$shiftIndex.slots.$slotIndex";
                    $slotId = $slotData['id'] ?? null;
                    if ($slotId !== null && (! $existingSlots->has($slotId) || $existingSlots->get($slotId)->shift_id !== (int) $shiftId)) {
                        throw ValidationException::withMessages(["$field.id" => 'This slot does not belong to this shift.']);
                    }
                    if ($slotId !== null) {
                        $slotIds[] = $slotId;
                    }
                    $this->mutation->validateRange($slotData['starts_at'], $slotData['ends_at'], $occurrence, "$field.starts_at");
                    if (in_array($slotData['callsign'], $callsigns, true)) {
                        throw ValidationException::withMessages(["$field.callsign" => 'A position can only appear once in a shift.']);
                    }
                    $callsigns[] = $slotData['callsign'];
                    foreach ($ranges[$slotData['callsign']] ?? [] as $range) {
                        if ($slotData['starts_at'] < $range['ends_at'] && $slotData['ends_at'] > $range['starts_at']) {
                            throw ValidationException::withMessages(["$field.starts_at" => 'This position overlaps a slot in another shift.']);
                        }
                    }
                    $ranges[$slotData['callsign']][] = $slotData;
                    $existingSlot = $slotId === null ? null : $existingSlots->get($slotId);
                    if ($existingSlot?->booked_by !== null && ($existingSlot->callsign !== $slotData['callsign']
                        || $existingSlot->starts_at->format('Y-m-d\TH:i') !== $slotData['starts_at']
                        || $existingSlot->ends_at->format('Y-m-d\TH:i') !== $slotData['ends_at'])) {
                        throw ValidationException::withMessages([$field => 'A booked slot cannot be changed. Its controller must withdraw first.']);
                    }
                }
            }
            foreach ($existingSlots as $slot) {
                if ($slot->booked_by !== null && ! in_array($slot->id, $slotIds)) {
                    throw ValidationException::withMessages(['shifts' => 'A booked slot cannot be removed. Its controller must withdraw first.']);
                }
            }
            $positionIds = [];
            foreach ($data['positions'] as $index => $positionData) {
                $positionId = $positionData['id'] ?? null;
                if ($positionId !== null && ! $existingPositions->has($positionId)) {
                    throw ValidationException::withMessages(["positions.$index.id" => 'This position does not belong to this roster.']);
                }
                if ($positionId !== null) {
                    $positionIds[] = $positionId;
                    if (in_array($positionId, $interestedPositionIds) && $existingPositions->get($positionId)->callsign !== $positionData['callsign']) {
                        throw ValidationException::withMessages(["positions.$index.callsign" => 'A position with submitted interest cannot be changed.']);
                    }
                }
            }
            if (array_diff($interestedPositionIds, $positionIds) !== []) {
                throw ValidationException::withMessages(['positions' => 'A position with submitted interest cannot be removed.']);
            }
            if ($data['is_open'] && ($data['mode'] === 'pre_slotted' ? $ranges === [] : $data['positions'] === [])) {
                throw ValidationException::withMessages(['is_open' => 'Add at least one position before opening the roster.']);
            }

            $roster->fill(['mode' => $data['mode'], 'is_open' => $data['is_open']]);
            if ($data['is_open'] && $roster->opened_at === null) {
                $roster->opened_at = now();
            }
            $roster->save();
            $roster->slots()->whereNotIn('roster_slots.id', $slotIds)->delete();
            $roster->shifts()->whereNotIn('id', $shiftIds)->delete();
            $roster->positions()->whereNotIn('id', $positionIds)->delete();

            /** Temporarily free unique callsigns so valid swaps retain slot and position identities. */
            foreach ($existingSlots->whereIn('id', $slotIds) as $slot) {
                if ($slot->booked_by === null) {
                    $slot->update(['callsign' => '_'.$slot->id]);
                }
            }
            foreach ($existingPositions->whereIn('id', $positionIds) as $position) {
                if (! in_array($position->id, $interestedPositionIds)) {
                    $position->update(['callsign' => '_'.$position->id]);
                }
            }
            foreach ($data['shifts'] as $shiftData) {
                $shift = isset($shiftData['id']) ? $existingShifts->get($shiftData['id']) : new RosterShift(['roster_id' => $roster->id]);
                $shift->fill(['name' => $shiftData['name']])->save();
                foreach ($shiftData['slots'] as $slotData) {
                    $slot = isset($slotData['id']) ? $existingSlots->get($slotData['id']) : new RosterSlot(['shift_id' => $shift->id]);
                    $slot->fill([
                        'callsign' => $slotData['callsign'],
                        'starts_at' => CarbonImmutable::parse($slotData['starts_at'], 'UTC'),
                        'ends_at' => CarbonImmutable::parse($slotData['ends_at'], 'UTC'),
                    ])->save();
                }
            }
            foreach ($data['positions'] as $positionData) {
                $position = isset($positionData['id']) ? $existingPositions->get($positionData['id']) : new RosterPosition(['roster_id' => $roster->id]);
                $position->fill(['callsign' => $positionData['callsign']])->save();
            }
            $this->audit->handle($roster, $before === [] ? 'created' : 'updated', $before, $roster->auditValues());

            return $roster;
        });
    }
}
