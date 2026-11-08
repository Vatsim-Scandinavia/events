import type { useConfirm } from '@/components/confirm-dialog';
import type { TimelineRow } from '@/components/staffing/timeline';
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
    // Every Section, even one with no positions under it.
    sections: { id: number; label: string | null; sort: number }[];
    interests: StaffInterest[];
    roster: RosterEntry[];
    controllers: Controller[];
    // Known positions to suggest when adding one; empty unless an Integration fills it.
    catalogue: CataloguePosition[];
};

/*
 * Edits to positions, Sections, shifts and the roster are kept in a draft on this page and
 * only reach controllers when staff press save. Keys identify positions, shifts and slots in
 * the draft: existing ones use their id, new ones get negative numbers. Sections always get
 * a negative key, as their ids could clash with positions' as timeline rows.
 */
export type DraftPosition = { kind: 'position'; key: number; callsign: string; frequency: string | null };
export type DraftSection = { kind: 'section'; key: number; id: number | null; label: string };
// Positions and Sections in the order shown, which is the order saved.
export type DraftRow = DraftPosition | DraftSection;
export type DraftShift = { key: number; id: number | null; position: number; start: number; end: number; booking: Shift['booking'] };
export type DraftSlot = { key: number; id: number | null; position: number; controller: Controller; start: number; end: number };
export type Draft = { rows: DraftRow[]; shifts: DraftShift[]; roster: DraftSlot[] };

export const CALLSIGN = /^[A-Z0-9]+(_[A-Z0-9]+)+$/;

export const positionsOf = (draft: Draft): DraftPosition[] => draft.rows.filter((row) => row.kind === 'position');

export function draftFrom({ positions, sections, roster }: StaffingPageProps, newKey: () => number): Draft {
    // Sections share the positions' numbering; on a tie the position comes first.
    const rows = [
        ...positions.map((position) => ({
            sort: position.sort,
            row: { kind: 'position', key: position.id, callsign: position.callsign, frequency: position.frequency } as DraftRow,
        })),
        ...sections.map((section) => ({
            sort: section.sort,
            row: { kind: 'section', key: newKey(), id: section.id, label: section.label ?? '' } as DraftRow,
        })),
    ].sort((a, b) => a.sort - b.sort);

    return {
        rows: rows.map(({ row }) => row),
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

// The draft's rows as timeline rows: positions hold shifts or roster entries, Sections are headings.
export function timelineRows({ draft, update }: DraftProps, highlighted?: (key: number) => boolean): TimelineRow[] {
    return draft.rows.map((row) =>
        row.kind === 'section'
            ? {
                  id: row.key,
                  label: row.label ? `section ${row.label}` : 'unnamed section',
                  section: {
                      name: row.label,
                      isNew: row.id === null,
                      onRename: (label: string) =>
                          update((current) => ({
                              ...current,
                              rows: current.rows.map((item) => (item.key === row.key ? { ...item, label } : item)),
                          })),
                  },
              }
            : { id: row.key, label: row.callsign, detail: row.frequency, highlighted: highlighted?.(row.key) ?? false },
    );
}

// Removes a Section, or a position with its shifts and roster entries.
export async function removeRow({ draft, update }: DraftProps, key: number, confirm: ReturnType<typeof useConfirm>) {
    const position = draft.rows.find((item) => item.key === key)!;

    if (position.kind === 'section') {
        return update((current) => ({ ...current, rows: current.rows.filter((item) => item.key !== key) }));
    }

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
        rows: current.rows.filter((item) => item.key !== key),
        shifts: current.shifts.filter((shift) => shift.position !== key),
        roster: current.roster.filter((slot) => slot.position !== key),
    }));
}

// Moves a position or Section to a new place in the draft's list, which is the order saved for the event.
export function moveRow({ update }: DraftProps, key: number, toIndex: number) {
    update((current) => {
        const moving = current.rows.find((item) => item.key === key)!;
        const rows = current.rows.filter((item) => item.key !== key);
        rows.splice(Math.max(0, Math.min(toIndex, rows.length)), 0, moving);

        return { ...current, rows };
    });
}

// Booked shifts that saving the draft would unbook: moved away from where they were booked, or removed.
export function unbookedBy(draft: Draft, saved: Draft): { shift: DraftShift; callsign: string }[] {
    return saved.shifts
        .filter((shift) => shift.booking)
        .filter((shift) => {
            const current = draft.shifts.find((item) => item.id === shift.id);

            return !current || current.position !== shift.position || current.start !== shift.start || current.end !== shift.end;
        })
        .map((shift) => ({ shift, callsign: positionsOf(saved).find((position) => position.key === shift.position)?.callsign ?? '' }));
}
