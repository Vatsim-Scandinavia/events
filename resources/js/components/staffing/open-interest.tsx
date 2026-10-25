import { router, useForm, usePage } from '@inertiajs/react';
import { CalendarClock, EyeOff, ListChecks, Plus, Users, X } from 'lucide-react';
import type { FormEvent } from 'react';
import SignInPrompt from '@/components/staffing/sign-in-prompt';
import { NotesIcon, PositionNote } from '@/components/staffing/staffing-notes';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Field, FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { describeTimezone } from '@/lib/time';
import type {
    AvailabilityWindow,
    EventStaffing,
    Interest,
    InterestWindowState,
    RosterEntry,
    StaffingPosition,
    StaffInterest,
} from '@/types/staffing';

/**
 * Staffing by open interest on the event page: controllers show their interest and
 * availability, and see the roster once Event Staff publish it.
 */
export default function OpenInterest({ staffing }: { staffing: Extract<EventStaffing, { mode: 'open_interest' }> }) {
    const { viewer, interest, occurrence, draft } = staffing;
    // Nobody can show interest in a Draft, so treat its window as not open.
    const open = interest.state === 'open' && !draft;

    return (
        <>
            {draft ? (
                <p className="flex items-start gap-2 text-sm text-muted-foreground">
                    <CalendarClock className="mt-0.5 size-4 shrink-0" />
                    Controllers can show interest once the event is published.
                </p>
            ) : (
                <WindowNotice state={interest.state} opensAt={interest.opensAt} closesAt={interest.closesAt} />
            )}

            {open && viewer.signedIn && viewer.eligible ? (
                <InterestForm occurrenceId={occurrence.id} positions={staffing.positions} mine={interest.mine} />
            ) : (
                <>
                    {interest.mine && <InterestSummary interest={interest.mine} />}
                    {open &&
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
                            <span className="flex flex-col">
                                <span className="font-mono">{position.callsign}</span>
                                <PositionNote note={position.note} />
                            </span>
                            <span className="ml-auto self-start font-mono text-xs text-muted-foreground">{position.frequency}</span>
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
                                <NotesIcon notes={entry.notes} className="ml-1.5" />
                            </td>
                            <td className="px-4 py-2.5 text-right font-mono whitespace-nowrap tabular-nums">{entry.time}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
