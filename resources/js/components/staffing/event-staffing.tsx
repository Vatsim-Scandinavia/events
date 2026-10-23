import { Link, router, useForm, usePage } from '@inertiajs/react';
import { CalendarClock, EyeOff, Headset, ListChecks, Plus, Users, X } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useConfirm } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Field, FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { describeTimezone } from '@/lib/time';
import { cn, signInUrl } from '@/lib/utils';
import type {
    AvailabilityWindow,
    EventStaffing,
    Interest,
    InterestWindowState,
    PositionWithShifts,
    RosterEntry,
    Shift,
    StaffingPosition,
    StaffInterest,
} from '@/types/staffing';

type Props = {
    eventId: number;
    staffing: Exclude<EventStaffing, { mode: 'none' }>;
};

/**
 * The controller-facing half of staffing on the event page: book shifts, or show
 * interest and see the published roster.
 */
export default function EventStaffing({ eventId, staffing }: Props) {
    return (
        <section aria-labelledby="staffing" className="flex flex-col gap-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 id="staffing" className="flex items-center gap-2 text-xl font-semibold tracking-tight">
                    <span className="text-muted-foreground">
                        <Headset className="size-5" />
                    </span>
                    Controller staffing
                </h2>
                {staffing.canManage && (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={`/events/${eventId}/staffing?occurrence=${staffing.occurrence.id}`}>Manage staffing</Link>
                    </Button>
                )}
            </div>

            {staffing.mode === 'shifts' ? <ShiftBoard staffing={staffing} /> : <OpenInterest staffing={staffing} />}
        </section>
    );
}

function ShiftBoard({ staffing }: { staffing: Extract<EventStaffing, { mode: 'shifts' }> }) {
    const { viewer, bookingOpen, occurrence } = staffing;

    const hint = !bookingOpen
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

function OpenInterest({ staffing }: { staffing: Extract<EventStaffing, { mode: 'open_interest' }> }) {
    const { viewer, interest, occurrence } = staffing;

    return (
        <>
            <WindowNotice state={interest.state} opensAt={interest.opensAt} closesAt={interest.closesAt} />

            {interest.state === 'open' && viewer.signedIn && viewer.eligible ? (
                <InterestForm occurrenceId={occurrence.id} positions={staffing.positions} mine={interest.mine} />
            ) : (
                <>
                    {interest.mine && <InterestSummary interest={interest.mine} />}
                    {interest.state === 'open' &&
                        (viewer.signedIn ? (
                            <p className="text-sm text-muted-foreground">You need a controller rating (S1 or above) to show interest.</p>
                        ) : (
                            <SignInPrompt>Sign in to show your interest in controlling this event.</SignInPrompt>
                        ))}
                    <PositionList positions={staffing.positions} />
                </>
            )}

            {interest.all && <InterestList interests={interest.all} />}
            <Roster entries={staffing.roster} published={staffing.rosterPublished} />
        </>
    );
}

// Everyone's interest, read-only, for staff and vACC Staff.
function InterestList({ interests }: { interests: StaffInterest[] }) {
    return (
        <div className="flex flex-col gap-3">
            <h3 className="flex items-center gap-2 font-medium">
                <Users className="size-4 text-muted-foreground" />
                Interest
                <Badge variant="outline">
                    <EyeOff />
                    Only staff can see this
                </Badge>
            </h3>
            {interests.length === 0 ? (
                <p className="text-sm text-muted-foreground">Nobody has shown interest yet.</p>
            ) : (
                <div className="overflow-x-auto rounded-xl bg-card ring-1 ring-foreground/10">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                            <tr>
                                <th scope="col" className="px-4 py-2.5 font-medium">
                                    Controller
                                </th>
                                <th scope="col" className="px-4 py-2.5 font-medium">
                                    Positions
                                </th>
                                <th scope="col" className="px-4 py-2.5 text-right font-medium">
                                    Available
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {interests.map((item) => (
                                <tr key={item.id} className="align-top">
                                    <td className="px-4 py-2.5">
                                        {item.controller.name}
                                        {item.controller.cid && <span className="ml-1.5 text-xs text-muted-foreground">{item.controller.cid}</span>}
                                        {item.notes && <p className="mt-1 text-xs whitespace-pre-line text-muted-foreground">{item.notes}</p>}
                                    </td>
                                    <td className="px-4 py-2.5 font-mono">{item.callsigns.join(', ')}</td>
                                    <td className="px-4 py-2.5 text-right font-mono whitespace-nowrap tabular-nums">{formatWindows(item.windows)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

// Brings people straight back to this event once they've signed in.
function SignInPrompt({ children }: { children: ReactNode }) {
    const { url } = usePage();

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl px-4 py-3 text-sm ring-1 ring-foreground/10">
            <span className="text-muted-foreground">{children}</span>
            <Button size="sm" asChild>
                <a href={signInUrl(url)}>Sign in with VATSIM</a>
            </Button>
        </div>
    );
}

function WindowNotice({ state, opensAt, closesAt }: { state: InterestWindowState; opensAt: string; closesAt: string }) {
    const text = {
        upcoming: `Controllers can show interest from ${opensAt}.`,
        open: `Show your interest and availability by ${closesAt}. Event Staff will publish a roster before the event.`,
        closed: `Interest closed ${closesAt}.`,
    }[state];

    return (
        <p className="flex items-start gap-2 text-sm text-muted-foreground">
            <CalendarClock className="mt-0.5 size-4 shrink-0" />
            {text}
        </p>
    );
}

function InterestForm({ occurrenceId, positions, mine }: { occurrenceId: number; positions: StaffingPosition[]; mine: Interest | null }) {
    const { displayTimezone } = usePage().props;
    const form = useForm({
        positions: mine?.positionIds ?? [],
        windows: mine?.windows.map(({ from, until }) => ({ from, until })) ?? [],
        notes: mine?.notes ?? '',
    });
    // Window errors come back keyed by index, e.g. "windows.1.from" or "windows.1" for the whole window.
    const errors = form.errors as Record<string, string | undefined>;

    const toggle = (id: number) =>
        form.setData(
            'positions',
            form.data.positions.includes(id) ? form.data.positions.filter((value) => value !== id) : [...form.data.positions, id],
        );

    const setWindow = (index: number, field: 'from' | 'until', value: string) =>
        form.setData(
            'windows',
            form.data.windows.map((item, i) => (i === index ? { ...item, [field]: value } : item)),
        );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(`/occurrences/${occurrenceId}/interest`, { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-5 rounded-xl bg-card p-4 ring-1 ring-foreground/10">
            <div className="flex items-center justify-between gap-2">
                <h3 className="font-medium">{mine ? 'Your interest' : 'Show interest'}</h3>
                {mine && <Badge variant="secondary">Saved</Badge>}
            </div>

            <fieldset className="flex flex-col gap-2">
                <legend className="mb-2 text-sm font-medium">Positions you'd like to control</legend>
                <div className="grid gap-2 sm:grid-cols-2">
                    {positions.map((position) => (
                        <Label
                            key={position.id}
                            className="cursor-pointer rounded-lg px-3 py-2.5 font-normal ring-1 ring-foreground/10 has-checked:bg-primary/10 has-checked:ring-primary/50"
                        >
                            <input
                                type="checkbox"
                                className="size-4 accent-primary"
                                checked={form.data.positions.includes(position.id)}
                                onChange={() => toggle(position.id)}
                            />
                            <span className="font-mono">{position.callsign}</span>
                            <span className="ml-auto font-mono text-xs text-muted-foreground">{position.frequency}</span>
                        </Label>
                    ))}
                </div>
                <FieldError message={form.errors.positions} />
            </fieldset>

            <fieldset className="flex flex-col gap-3">
                <legend className="mb-2 text-sm font-medium">When you're available ({describeTimezone(displayTimezone)})</legend>
                {form.data.windows.length === 0 && (
                    <p className="text-sm text-muted-foreground">Leave this empty if you're available for the whole event.</p>
                )}
                {form.data.windows.map((availability, index) => {
                    const fromError = errors[`windows.${index}.from`];
                    const untilError = errors[`windows.${index}.until`];
                    const windowError = errors[`windows.${index}`];

                    return (
                        <div key={index} className="flex flex-col gap-1.5">
                            <div className="flex items-end gap-2">
                                <Field label="From" className="flex-1">
                                    <Input
                                        type="time"
                                        required
                                        value={availability.from}
                                        onChange={(event) => setWindow(index, 'from', event.target.value)}
                                        aria-invalid={!!(fromError || windowError)}
                                    />
                                </Field>
                                <Field label="Until" className="flex-1">
                                    <Input
                                        type="time"
                                        required
                                        value={availability.until}
                                        onChange={(event) => setWindow(index, 'until', event.target.value)}
                                        aria-invalid={!!(untilError || windowError)}
                                    />
                                </Field>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Remove this window"
                                    onClick={() =>
                                        form.setData(
                                            'windows',
                                            form.data.windows.filter((_, i) => i !== index),
                                        )
                                    }
                                >
                                    <X />
                                </Button>
                            </div>
                            {/* Below the row rather than under each input, so the times stay aligned. */}
                            <FieldError message={fromError ?? untilError ?? windowError} />
                        </div>
                    );
                })}
                <FieldError message={errors.windows} />
                {form.data.windows.length < 6 && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="self-start"
                        onClick={() => form.setData('windows', [...form.data.windows, { from: '', until: '' }])}
                    >
                        <Plus />
                        {form.data.windows.length === 0 ? 'Add a time window' : 'Add another window'}
                    </Button>
                )}
            </fieldset>

            <Field label="Notes for Event Staff (optional)" error={form.errors.notes}>
                <Textarea value={form.data.notes} maxLength={500} onChange={(event) => form.setData('notes', event.target.value)} />
            </Field>

            <p className="text-xs text-muted-foreground">Only you, Event Staff and vACC Staff can see your interest.</p>

            <div className="flex flex-wrap gap-2">
                <Button type="submit" disabled={form.processing}>
                    {mine ? 'Update interest' : 'Show interest'}
                </Button>
                {mine && (
                    <Button
                        type="button"
                        variant="ghost"
                        disabled={form.processing}
                        onClick={() => router.delete(`/occurrences/${occurrenceId}/interest`, { preserveScroll: true })}
                    >
                        Withdraw
                    </Button>
                )}
            </div>
        </form>
    );
}

function InterestSummary({ interest }: { interest: Interest }) {
    return (
        <div className="flex flex-col gap-1 rounded-xl bg-card p-4 text-sm ring-1 ring-foreground/10">
            <span className="font-medium">Your interest</span>
            <span className="text-muted-foreground">
                <span className="font-mono">{interest.callsigns.join(', ')}</span> · available {formatWindows(interest.windows)}
            </span>
        </div>
    );
}

export function formatWindows(windows: AvailabilityWindow[]): string {
    return windows.map((item) => item.time).join(', ');
}

function PositionList({ positions }: { positions: StaffingPosition[] }) {
    if (positions.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-1.5">
            {positions.map((position) => (
                <Badge key={position.id} variant="outline" className="font-mono">
                    {position.callsign}
                </Badge>
            ))}
        </div>
    );
}

function Roster({ entries, published }: { entries: RosterEntry[] | null; published: boolean }) {
    if (entries === null) {
        return null;
    }

    return (
        <div className="flex flex-col gap-3">
            <h3 className="flex items-center gap-2 font-medium">
                <ListChecks className="size-4 text-muted-foreground" />
                Roster
                {!published && (
                    <Badge variant="outline">
                        <EyeOff />
                        Not published, only staff can see this
                    </Badge>
                )}
            </h3>
            {entries.length === 0 ? (
                <p className="text-sm text-muted-foreground">Nobody has been rostered yet.</p>
            ) : (
                <RosterTable entries={entries} />
            )}
        </div>
    );
}

function RosterTable({ entries }: { entries: RosterEntry[] }) {
    return (
        <div className="overflow-x-auto rounded-xl bg-card ring-1 ring-foreground/10">
            <table className="w-full text-sm">
                <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                    <tr>
                        <th scope="col" className="px-4 py-2.5 font-medium">
                            Position
                        </th>
                        <th scope="col" className="px-4 py-2.5 font-medium">
                            Controller
                        </th>
                        <th scope="col" className="px-4 py-2.5 text-right font-medium">
                            Time
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {entries.map((entry) => (
                        <tr key={entry.id}>
                            <td className="px-4 py-2.5 font-mono">{entry.callsign}</td>
                            <td className="px-4 py-2.5">
                                {entry.controller.name}
                                {entry.controller.cid && <span className="ml-1.5 text-xs text-muted-foreground">{entry.controller.cid}</span>}
                            </td>
                            <td className="px-4 py-2.5 text-right font-mono whitespace-nowrap tabular-nums">{entry.time}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
