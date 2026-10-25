import type { useConfirm } from '@/components/confirm-dialog';
import type { HubEvent } from '@/types/event';
import type {
    CataloguePosition,
    Controller,
    PositionWithShifts,
    RosterEntry,
    Shift,
    StaffingMode,
    StaffInterest,
    StaffOccurrence,
} from '@/types/staffing';

export type StaffingPageProps = {
    event: HubEvent;
    settings: { mode: StaffingMode; interestOpensHours: number; interestClosesHours: number };
    occurrence: StaffOccurrence;
    positions: PositionWithShifts[];
    interests: StaffInterest[];
    roster: RosterEntry[];
    controllers: Controller[];
    // Known positions to suggest when adding one; empty unless an Integration fills it.
    catalogue: CataloguePosition[];
};

/*
 * Edits to positions, shifts and the roster are kept in a draft on this page and only
 * reach controllers when staff press save. Keys identify positions, shifts and slots in
 * the draft: existing ones use their id, new ones get negative numbers.
 */
export type DraftPosition = { key: number; callsign: string; frequency: string | null };
export type DraftShift = { key: number; id: number | null; position: number; start: number; end: number; booking: Shift['booking'] };
export type DraftSlot = { key: number; id: number | null; position: number; controller: Controller; start: number; end: number };
export type Draft = { positions: DraftPosition[]; shifts: DraftShift[]; roster: DraftSlot[] };

export const CALLSIGN = /^[A-Z0-9]+(_[A-Z0-9]+)+$/;

export function draftFrom({ positions, roster }: StaffingPageProps): Draft {
    return {
        positions: positions.map((position) => ({ key: position.id, callsign: position.callsign, frequency: position.frequency })),
        shifts: positions.flatMap((position) =>
            position.shifts.map((shift) => ({
                key: shift.id,
                id: shift.id,
                position: position.id,
                start: shift.startOffset,
                end: shift.endOffset,
                booking: shift.booking,
            })),
        ),
        roster: roster.map((entry) => ({
            key: entry.id,
            id: entry.id,
            position: entry.positionId,
            controller: entry.controller,
            start: entry.startOffset,
            end: entry.endOffset,
        })),
    };
}

export type DraftProps = {
    draft: Draft;
    update: (change: (draft: Draft) => Draft) => void;
    newKey: () => number;
};

// Removes a position from the draft, with its shifts and roster entries.
export async function removePosition({ draft, update }: DraftProps, key: number, confirm: ReturnType<typeof useConfirm>) {
    const position = draft.positions.find((item) => item.key === key)!;
    const booked = draft.shifts.filter((shift) => shift.position === key && shift.booking).length;

    if (
        booked &&
        !(await confirm({
            title: `Remove ${position.callsign}?`,
            description: `It has ${booked} booked shift${booked > 1 ? 's' : ''}. Removing the position unbooks ${booked > 1 ? 'those controllers' : 'that controller'} when you save.`,
            confirmLabel: 'Remove position',
            destructive: true,
        }))
    ) {
        return;
    }

    update((current) => ({
        positions: current.positions.filter((item) => item.key !== key),
        shifts: current.shifts.filter((shift) => shift.position !== key),
        roster: current.roster.filter((slot) => slot.position !== key),
    }));
}

// Booked shifts that saving the draft would unbook: moved away from where they were booked, or removed.
export function unbookedBy(draft: Draft, saved: Draft): { shift: DraftShift; callsign: string }[] {
    return saved.shifts
        .filter((shift) => shift.booking)
        .filter((shift) => {
            const current = draft.shifts.find((item) => item.id === shift.id);

            return !current || current.position !== shift.position || current.start !== shift.start || current.end !== shift.end;
        })
        .map((shift) => ({ shift, callsign: saved.positions.find((position) => position.key === shift.position)?.callsign ?? '' }));
}
