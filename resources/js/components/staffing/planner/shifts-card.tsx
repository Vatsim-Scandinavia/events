import { useConfirm } from '@/components/confirm-dialog';
import Timeline, { type TimelineItem } from '@/components/staffing/timeline';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { StaffOccurrence } from '@/types/staffing';
import { AddPositionRow } from './add-position-row';
import { removePosition, unbookedBy, type Draft, type DraftProps, type DraftShift } from './draft';

export function ShiftsCard({ occurrence, draft, saved, update, newKey }: DraftProps & { occurrence: StaffOccurrence; saved: Draft }) {
    const confirm = useConfirm();
    const unbooking = new Set(unbookedBy(draft, saved).map(({ shift }) => shift.id));

    const items: TimelineItem[] = draft.shifts.map((shift) => {
        const name = shift.booking?.controller.name;

        return {
            id: shift.key,
            rowId: shift.position,
            start: shift.start,
            end: shift.end,
            label: name ? (unbooking.has(shift.id) ? `Unbooks ${name}` : name) : shift.id ? 'Open' : 'New',
            tone: name ? (unbooking.has(shift.id) ? 'warning' : 'default') : 'muted',
        };
    });

    const setShift = (key: number, change: Partial<DraftShift>) =>
        update((current) => ({ ...current, shifts: current.shifts.map((shift) => (shift.key === key ? { ...shift, ...change } : shift)) }));

    // Ask before the first move of a booked shift; moving it back where it was keeps the booking.
    const move = async (key: number, start: number, end: number) => {
        const shift = draft.shifts.find((item) => item.key === key)!;
        const name = shift.booking?.controller.name;

        if (
            name &&
            !unbooking.has(shift.id) &&
            !(await confirm({
                title: 'Move a booked shift?',
                description: `${name} has booked this shift. Moving it will unbook them when you save, and they’ll need to book again.`,
                confirmLabel: 'Move and unbook',
                destructive: true,
            }))
        ) {
            return;
        }

        setShift(key, { start, end });
    };

    const remove = async (key: number) => {
        const name = draft.shifts.find((shift) => shift.key === key)?.booking?.controller.name;

        if (
            name &&
            !(await confirm({
                title: 'Remove a booked shift?',
                description: `${name} has booked this shift. Removing it will unbook them when you save.`,
                confirmLabel: 'Remove and unbook',
                destructive: true,
            }))
        ) {
            return;
        }

        update((current) => ({ ...current, shifts: current.shifts.filter((shift) => shift.key !== key) }));
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Positions and shifts</CardTitle>
                <CardDescription>
                    Add the positions you want staffed below the timeline. Click or drag along a position to add a shift, drag a shift to move it, and
                    drag its edges to change its times. Shifts on a position can’t overlap. They apply to every date of the event; bookings shown are
                    for {occurrence.date}. Moving or removing a booked shift unbooks the controller.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Timeline
                    noun="shift"
                    startsAt={occurrence.startsAt}
                    duration={occurrence.durationMinutes}
                    rows={draft.positions.map((position) => ({ id: position.key, label: position.callsign, detail: position.frequency }))}
                    items={items}
                    emptyHint="Click or drag here to add a shift"
                    onRemoveRow={(key) => removePosition({ draft, update, newKey }, key, confirm)}
                    footer={<AddPositionRow draft={draft} update={update} newKey={newKey} />}
                    onCreate={(position, start, end) =>
                        update((current) => ({
                            ...current,
                            shifts: [...current.shifts, { key: newKey(), id: null, position, start, end, booking: null }],
                        }))
                    }
                    onChange={(item, _position, start, end) => move(item.id, start, end)}
                    onDelete={(item) => remove(item.id)}
                />
            </CardContent>
        </Card>
    );
}
