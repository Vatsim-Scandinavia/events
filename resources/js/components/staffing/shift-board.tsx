import { router } from '@inertiajs/react';
import { useConfirm } from '@/components/confirm-dialog';
import SignInPrompt from '@/components/staffing/sign-in-prompt';
import { NotesIcon, PositionNote } from '@/components/staffing/staffing-notes';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { EventStaffing, PositionWithShifts, Shift } from '@/types/staffing';

/**
 * Staffing by shifts on the event page: controllers book a free shift on a position.
 */
export default function ShiftBoard({ staffing }: { staffing: Extract<EventStaffing, { mode: 'shifts' }> }) {
    const { viewer, bookingOpen: open, occurrence, draft } = staffing;
    const bookingOpen = open && !draft;

    const hint = draft
        ? 'Controllers can book shifts once the event is published.'
        : !bookingOpen
          ? 'Booking closed when the event started.'
          : !viewer.signedIn
            ? 'Sign in to book a shift.'
            : !viewer.eligible
              ? 'You need a controller rating (S1 or above) to book shifts.'
              : 'Pick a free shift to book it. You can cancel until the event starts.';

    return (
        <>
            {bookingOpen && !viewer.signedIn ? <SignInPrompt>{hint}</SignInPrompt> : <p className="text-sm text-muted-foreground">{hint}</p>}
            {staffing.positions.length === 0 ? (
                <p className="text-sm text-muted-foreground">Positions for this event haven't been set up yet.</p>
            ) : (
                <ShiftTable positions={staffing.positions} occurrenceId={occurrence.id} canBook={bookingOpen && viewer.signedIn && viewer.eligible} />
            )}
        </>
    );
}

// One row per shift, with each position's callsign spanning its shifts, so positions with
// many shifts don't leave gaps next to positions with few.
function ShiftTable({ positions, occurrenceId, canBook }: { positions: PositionWithShifts[]; occurrenceId: number; canBook: boolean }) {
    return (
        <div className="overflow-x-auto rounded-xl bg-card ring-1 ring-foreground/10">
            <table className="w-full text-sm">
                <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                    <tr>
                        <th scope="col" className="px-4 py-2.5 font-medium">
                            Position
                        </th>
                        <th scope="col" className="px-4 py-2.5 font-medium">
                            Shift
                        </th>
                        <th scope="col" className="px-4 py-2.5 text-right font-medium">
                            Controller
                        </th>
                    </tr>
                </thead>
                {positions.map((position) => (
                    // The server only sends positions that have shifts.
                    <tbody key={position.id} className="border-b last:border-b-0 [&>tr+tr]:border-t [&>tr+tr]:border-dashed">
                        {position.shifts.map((shift, index) => (
                            // Style the shift's own cells, not the callsign cell that spans the position's
                            // rows. Shifts taken by someone else read as disabled; the rest highlight on
                            // hover or focus so the Book button is easy to match.
                            <tr
                                key={shift.id}
                                className={cn(
                                    '[&>td]:transition-colors',
                                    shift.booking && !shift.booking.isMine
                                        ? '[&>td]:bg-muted/50 [&>td]:text-muted-foreground'
                                        : 'has-[td_:focus-visible]:[&>td]:bg-muted/60 has-[td:hover]:[&>td]:bg-muted/60',
                                )}
                            >
                                {index === 0 && <PositionCell position={position} rows={position.shifts.length} />}
                                <td className="px-4 py-2 font-mono whitespace-nowrap tabular-nums">{shift.time}</td>
                                <td className="px-4 py-1.5 text-right">
                                    <ShiftStatus shift={shift} callsign={position.callsign} occurrenceId={occurrenceId} canBook={canBook} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                ))}
            </table>
        </div>
    );
}

function PositionCell({ position, rows }: { position: PositionWithShifts; rows: number }) {
    return (
        <th scope="rowgroup" rowSpan={rows} className="px-4 py-2 text-left align-top font-normal">
            <span className="block font-mono font-medium">{position.callsign}</span>
            {position.frequency && <span className="block font-mono text-xs text-muted-foreground tabular-nums">{position.frequency}</span>}
            <PositionNote note={position.note} />
        </th>
    );
}

function ShiftStatus({ shift, callsign, occurrenceId, canBook }: { shift: Shift; callsign: string; occurrenceId: number; canBook: boolean }) {
    const confirm = useConfirm();

    if (shift.booking) {
        const booking = shift.booking;

        const cancel = async () => {
            const confirmed = await confirm({
                title: booking.isMine ? 'Cancel your booking?' : `Cancel ${booking.controller.name}'s booking?`,
                description: `${callsign} ${shift.time} will be open for other controllers to book.`,
                confirmLabel: 'Cancel booking',
                cancelLabel: 'Keep booking',
                destructive: true,
            });

            if (confirmed) {
                router.delete(`/bookings/${booking.id}`, { preserveScroll: true });
            }
        };

        return (
            <span className="inline-flex items-center gap-2">
                <span className={cn(shift.booking.isMine && 'font-medium text-primary')}>
                    {shift.booking.isMine ? 'You' : shift.booking.controller.name}
                    {!shift.booking.isMine && shift.booking.controller.cid && (
                        <span className="ml-1 text-xs text-muted-foreground">{shift.booking.controller.cid}</span>
                    )}
                </span>
                <NotesIcon notes={shift.booking.notes} />
                {shift.booking.canCancel && (
                    <Button size="xs" variant="ghost" onClick={cancel}>
                        Cancel
                    </Button>
                )}
            </span>
        );
    }

    if (canBook) {
        return (
            <Button
                size="xs"
                variant="outline"
                onClick={() => router.post(`/occurrences/${occurrenceId}/shifts/${shift.id}/booking`, {}, { preserveScroll: true })}
            >
                Book
            </Button>
        );
    }

    return <span className="text-muted-foreground">Open</span>;
}
