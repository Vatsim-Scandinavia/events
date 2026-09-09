import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, ChevronRight, Pencil } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import AlertError from '@/components/alert-error';
import { RosterEditor } from '@/components/roster-editor';
import { RosterInterestForm } from '@/components/roster-interest-form';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { eventTime } from '@/lib/event-time';
import { show } from '@/routes/events';
import { show as showRoster } from '@/routes/events/roster';
import { index as rosters } from '@/routes/rosters';
import { destroy, store, withdraw } from '@/routes/roster/bookings';
import { destroy as withdrawInterest } from '@/routes/roster/interest';
import type {
    EventRoster,
    RosterBooking,
    RosterPageProps,
    RosterSlot,
} from '@/types/rosters';

function staffingTime(value: string) {
    return eventTime(value.length === 16 ? value + ':00Z' : value);
}

function BookingButton({
    rosterId,
    slot,
    ownBooking,
    canBook,
    occurrenceDate,
    autoSelectOccurrence,
}: {
    rosterId: number;
    slot: RosterSlot;
    ownBooking: boolean;
    canBook: boolean;
    occurrenceDate: string;
    autoSelectOccurrence: boolean;
}) {
    const form = useForm({
        occurrence_date: occurrenceDate,
        ...(autoSelectOccurrence ? { return_to_current: true } : {}),
    });
    if (!ownBooking && (!canBook || slot.booking)) return null;

    return (
        <div className="flex flex-col items-start gap-2">
            <Button
                type="button"
                variant={ownBooking ? 'outline' : 'default'}
                size="sm"
                disabled={form.processing}
                onClick={() =>
                    form.submit(
                        (ownBooking ? destroy : store)({
                            roster: rosterId,
                            slot: slot.id,
                        }),
                        {
                            preserveScroll: true,
                            onSuccess: () =>
                                toast.success(
                                    ownBooking
                                        ? 'Booking withdrawn.'
                                        : 'Position booked.',
                                ),
                        },
                    )
                }
            >
                {form.processing ? <Spinner data-icon="inline-start" /> : null}
                {ownBooking ? 'Withdraw booking' : 'Book position'}
            </Button>
            {form.hasErrors ? (
                <AlertError errors={Object.values(form.errors)} />
            ) : null}
        </div>
    );
}

function HistoricalWithdrawal({
    rosterId,
    booking,
    occurrenceDate,
    autoSelectOccurrence,
}: {
    rosterId: number;
    booking: RosterBooking;
    occurrenceDate: string;
    autoSelectOccurrence: boolean;
}) {
    const form = useForm({
        occurrence_date: occurrenceDate,
        ...(autoSelectOccurrence ? { return_to_current: true } : {}),
    });
    return (
        <div className="flex flex-col gap-2">
            <Button
                variant="outline"
                size="sm"
                disabled={form.processing}
                onClick={() =>
                    form.submit(
                        withdraw({ roster: rosterId, booking: booking.id }),
                        {
                            preserveScroll: true,
                            onSuccess: () =>
                                toast.success('Booking withdrawn.'),
                        },
                    )
                }
            >
                {form.processing ? <Spinner data-icon="inline-start" /> : null}
                Withdraw booking
            </Button>
            {form.hasErrors ? (
                <AlertError errors={Object.values(form.errors)} />
            ) : null}
        </div>
    );
}

function RecordedInterestWithdrawal({
    rosterId,
    occurrenceDate,
    autoSelectOccurrence,
}: {
    rosterId: number;
    occurrenceDate: string;
    autoSelectOccurrence: boolean;
}) {
    const form = useForm({
        occurrence_date: occurrenceDate,
        ...(autoSelectOccurrence ? { return_to_current: true } : {}),
    });
    return (
        <div className="flex flex-col gap-2">
            <Button
                variant="outline"
                size="sm"
                disabled={form.processing}
                onClick={() =>
                    form.submit(withdrawInterest(rosterId), {
                        preserveScroll: true,
                        onSuccess: () => toast.success('Interest withdrawn.'),
                    })
                }
            >
                {form.processing ? <Spinner data-icon="inline-start" /> : null}
                Withdraw interest
            </Button>
            {form.hasErrors ? (
                <AlertError errors={Object.values(form.errors)} />
            ) : null}
        </div>
    );
}

function InterestSubmissions({
    roster,
    currentUserCid,
    occurrenceDate,
    autoSelectOccurrence,
}: {
    roster: EventRoster;
    currentUserCid: number;
    occurrenceDate: string;
    autoSelectOccurrence: boolean;
}) {
    const callsigns = new Map(
        roster.positions.map((position) => [position.id, position.callsign]),
    );
    return (
        <Card>
            <CardHeader>
                <CardTitle>Controller interest</CardTitle>
                <CardDescription>
                    {roster.interests.length}{' '}
                    {roster.interests.length === 1
                        ? 'controller has'
                        : 'controllers have'}{' '}
                    submitted interest. Availability is shown in UTC.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {roster.interests.length ? (
                    <ul className="flex flex-col gap-5">
                        {roster.interests.map((interest) => (
                            <li
                                key={interest.id}
                                className="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:justify-between"
                            >
                                <div className="flex min-w-0 flex-col gap-2">
                                    <p className="font-medium">
                                        {interest.user.name}{' '}
                                        <span className="text-muted-foreground text-sm font-normal">
                                            · {interest.user.cid}
                                        </span>
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        {(
                                            interest.position_callsigns ??
                                            interest.position_ids.map((id) =>
                                                callsigns.get(id),
                                            )
                                        ).map((callsign, index) => (
                                            <Badge
                                                key={index}
                                                variant="outline"
                                            >
                                                {callsign}
                                            </Badge>
                                        ))}
                                    </div>
                                </div>
                                <ul className="flex flex-col gap-1 text-sm">
                                    {interest.availability.map(
                                        (window, index) => (
                                            <li key={index}>
                                                {staffingTime(window.starts_at)}{' '}
                                                – {staffingTime(window.ends_at)}{' '}
                                                Z
                                            </li>
                                        ),
                                    )}
                                </ul>
                                {roster.mode !== 'open_interest' &&
                                interest.user.cid === currentUserCid ? (
                                    <RecordedInterestWithdrawal
                                        key={interest.id + '-' + occurrenceDate}
                                        rosterId={roster.id}
                                        occurrenceDate={occurrenceDate}
                                        autoSelectOccurrence={
                                            autoSelectOccurrence
                                        }
                                    />
                                ) : null}
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-muted-foreground text-sm">
                        No controller interest submitted yet.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

export default function EventRosterPage({
    event,
    occurrence,
    roster,
    canManage,
    canParticipate,
    canViewEvent,
    currentUserCid,
    occurrenceOptions,
    nextOccurrenceDate,
    autoSelectOccurrence,
}: RosterPageProps) {
    const [editing, setEditing] = useState(!roster);
    const canSignUp =
        !!roster?.is_open &&
        canParticipate &&
        occurrence.status === 'scheduled' &&
        !occurrence.has_ended;
    const ownInterest = roster?.interests.find(
        (interest) => interest.user.cid === currentUserCid,
    );
    const options = occurrenceOptions.some(
        (option) => option.date === occurrence.date,
    )
        ? occurrenceOptions
        : [...occurrenceOptions, occurrence].sort((first, second) =>
              first.date.localeCompare(second.date),
          );
    const representedBookingIds = new Set(
        roster?.shifts.flatMap((shift) =>
            shift.slots.flatMap((slot) =>
                slot.booking ? [slot.booking.id] : [],
            ),
        ) ?? [],
    );
    const extraBookings =
        roster?.bookings?.filter(
            (booking) => !representedBookingIds.has(booking.id),
        ) ?? [];

    useEffect(() => {
        if (
            !autoSelectOccurrence ||
            occurrence.has_ended ||
            !occurrence.ends_at ||
            editing
        )
            return;
        const endsAt = new Date(occurrence.ends_at).getTime();
        let timer: ReturnType<typeof setTimeout>;
        const advance = () => {
            const remaining = endsAt - Date.now();
            if (remaining > 0) {
                timer = setTimeout(
                    advance,
                    Math.min(remaining + 1000, 2147483647),
                );
                return;
            }
            router.visit(showRoster({ event: event.id }), {
                preserveScroll: true,
            });
        };
        timer = setTimeout(
            advance,
            Math.min(Math.max(endsAt - Date.now() + 1000, 1000), 2147483647),
        );
        return () => clearTimeout(timer);
    }, [
        autoSelectOccurrence,
        editing,
        event.id,
        occurrence.ends_at,
        occurrence.has_ended,
    ]);

    return (
        <>
            <Head title={event.title + ' · Roster'} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-8">
                <Link
                    href={canViewEvent ? show(event.id) : rosters()}
                    className="text-muted-foreground flex w-fit items-center gap-2 text-sm hover:underline"
                >
                    <ArrowLeft className="size-4" />
                    {canViewEvent ? 'Back to event' : 'All rosters'}
                </Link>
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex min-w-0 flex-col gap-3">
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="outline">Roster</Badge>
                            {roster ? (
                                <Badge variant="secondary">
                                    {roster.mode === 'pre_slotted'
                                        ? 'Pre-slotted'
                                        : 'Open interest'}
                                </Badge>
                            ) : null}
                            {roster ? (
                                <Badge
                                    variant={
                                        roster.is_open &&
                                        !occurrence.has_ended &&
                                        occurrence.status === 'scheduled'
                                            ? 'default'
                                            : 'outline'
                                    }
                                >
                                    {occurrence.status !== 'scheduled'
                                        ? 'Signups unavailable'
                                        : occurrence.has_ended
                                          ? 'Occurrence ended'
                                          : roster.is_open
                                            ? 'Signups open'
                                            : 'Signups closed'}
                                </Badge>
                            ) : null}
                        </div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {event.title}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {occurrence.starts_at && occurrence.ends_at
                                ? eventTime(occurrence.starts_at) +
                                  ' – ' +
                                  eventTime(occurrence.ends_at) +
                                  ' Z'
                                : occurrence.date}
                        </p>
                    </div>
                    {canManage &&
                    roster &&
                    !editing &&
                    occurrence.status === 'scheduled' ? (
                        <Button
                            variant="outline"
                            onClick={() => setEditing(true)}
                        >
                            <Pencil data-icon="inline-start" />
                            Edit event roster
                        </Button>
                    ) : null}
                </header>
                <div className="flex flex-wrap items-end gap-4">
                    <div className="flex min-w-0 flex-col gap-2">
                        <Label htmlFor="roster-occurrence">
                            Occurrence ({event.timezone})
                        </Label>
                        <Select
                            value={occurrence.date}
                            onValueChange={(date) =>
                                router.visit(
                                    showRoster({ event: event.id, date }),
                                )
                            }
                        >
                            <SelectTrigger
                                id="roster-occurrence"
                                className="w-full sm:min-w-64"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    {options.map((option) => (
                                        <SelectItem
                                            key={option.date}
                                            value={option.date}
                                        >
                                            {option.date}
                                            {option.status !== 'scheduled'
                                                ? ' · ' + option.status
                                                : ''}
                                        </SelectItem>
                                    ))}
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    {!autoSelectOccurrence ? (
                        <Button asChild variant="outline">
                            <Link href={showRoster({ event: event.id })}>
                                Current or next occurrence
                            </Link>
                        </Button>
                    ) : null}
                    {nextOccurrenceDate ? (
                        <Button asChild variant="outline">
                            <Link
                                href={showRoster({
                                    event: event.id,
                                    date: nextOccurrenceDate,
                                })}
                            >
                                Next occurrence
                                <ChevronRight data-icon="inline-end" />
                            </Link>
                        </Button>
                    ) : null}
                    <p className="text-muted-foreground basis-full text-sm">
                        {autoSelectOccurrence
                            ? 'Showing the current or next occurrence. This view advances after it ends. '
                            : ''}
                        Bookings and interest apply only to {occurrence.date}.
                    </p>
                </div>
                {occurrence.status !== 'scheduled' ? (
                    <Alert>
                        <AlertTitle>Occurrence {occurrence.status}</AlertTitle>
                        <AlertDescription>
                            {occurrence.reason ||
                                'Signups are unavailable for this occurrence.'}
                        </AlertDescription>
                    </Alert>
                ) : null}
                {roster && (!roster.is_open || occurrence.has_ended) ? (
                    <Alert>
                        <AlertTitle>
                            {occurrence.has_ended
                                ? 'This occurrence has ended'
                                : 'Signups are closed'}
                        </AlertTitle>
                        <AlertDescription>
                            Existing bookings and interest are retained.
                            Controllers can withdraw their submissions.
                        </AlertDescription>
                    </Alert>
                ) : null}
                {canManage && editing && occurrence.status === 'scheduled' ? (
                    <RosterEditor
                        key={event.id + '-' + occurrence.date}
                        event={event}
                        occurrence={occurrence}
                        roster={roster}
                        autoSelectOccurrence={autoSelectOccurrence}
                        onClose={() => setEditing(false)}
                    />
                ) : null}
                {!roster && !canManage ? (
                    <Alert>
                        <AlertTitle>No roster yet</AlertTitle>
                        <AlertDescription>
                            The coordinator has not set up a roster for this
                            occurrence.
                        </AlertDescription>
                    </Alert>
                ) : null}
                {roster?.mode === 'pre_slotted' ? (
                    <div className="flex flex-col gap-6">
                        {roster.shifts.map((shift) => (
                            <Card key={shift.id}>
                                <CardHeader>
                                    <CardTitle>{shift.name}</CardTitle>
                                    <CardDescription>
                                        {
                                            shift.slots.filter(
                                                (slot) => slot.booking,
                                            ).length
                                        }{' '}
                                        of {shift.slots.length} positions booked
                                        · All times UTC
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <ul className="flex flex-col gap-4">
                                        {shift.slots.map((slot) => {
                                            const ownBooking =
                                                slot.booking?.cid ===
                                                currentUserCid;
                                            return (
                                                <li
                                                    key={slot.id}
                                                    className="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
                                                >
                                                    <div className="flex min-w-0 flex-col gap-1">
                                                        <span className="font-mono font-semibold">
                                                            {slot.callsign}
                                                        </span>
                                                        <span className="text-muted-foreground text-sm">
                                                            {slot.starts_at &&
                                                            slot.ends_at
                                                                ? staffingTime(
                                                                      slot.starts_at,
                                                                  ) +
                                                                  ' – ' +
                                                                  staffingTime(
                                                                      slot.ends_at,
                                                                  ) +
                                                                  ' Z'
                                                                : 'Unavailable on this occurrence'}
                                                        </span>
                                                        <span className="text-sm">
                                                            {slot.booking
                                                                ? ownBooking
                                                                    ? 'Your booking'
                                                                    : slot
                                                                          .booking
                                                                          .name
                                                                : 'Available'}
                                                        </span>
                                                    </div>
                                                    <BookingButton
                                                        key={
                                                            slot.id +
                                                            '-' +
                                                            occurrence.date
                                                        }
                                                        rosterId={roster.id}
                                                        occurrenceDate={
                                                            occurrence.date
                                                        }
                                                        autoSelectOccurrence={
                                                            autoSelectOccurrence
                                                        }
                                                        slot={slot}
                                                        ownBooking={ownBooking}
                                                        canBook={
                                                            canSignUp &&
                                                            slot.can_book
                                                        }
                                                    />
                                                </li>
                                            );
                                        })}
                                    </ul>
                                    {!shift.slots.length ? (
                                        <p className="text-muted-foreground text-sm">
                                            No positions in this shift.
                                        </p>
                                    ) : null}
                                </CardContent>
                            </Card>
                        ))}
                        {!roster.shifts.length ? (
                            <Alert>
                                <AlertTitle>No shifts yet</AlertTitle>
                                <AlertDescription>
                                    The coordinator is preparing positions for
                                    this roster.
                                </AlertDescription>
                            </Alert>
                        ) : null}
                    </div>
                ) : null}
                {roster && extraBookings.length ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                Other bookings for this occurrence
                            </CardTitle>
                            <CardDescription>
                                These bookings keep the position and times
                                originally booked.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="flex flex-col gap-4">
                                {extraBookings.map((booking) => (
                                    <li
                                        key={booking.id}
                                        className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4"
                                    >
                                        <div className="flex flex-col gap-1">
                                            <span className="font-mono font-semibold">
                                                {booking.callsign}
                                            </span>
                                            <span className="text-muted-foreground text-sm">
                                                {booking.shift_name} ·{' '}
                                                {staffingTime(
                                                    booking.starts_at,
                                                )}{' '}
                                                –{' '}
                                                {staffingTime(booking.ends_at)}{' '}
                                                Z
                                            </span>
                                            <span className="text-sm">
                                                {booking.user.name}
                                            </span>
                                        </div>
                                        {booking.can_withdraw ? (
                                            <HistoricalWithdrawal
                                                key={
                                                    booking.id +
                                                    '-' +
                                                    occurrence.date
                                                }
                                                rosterId={roster.id}
                                                booking={booking}
                                                occurrenceDate={occurrence.date}
                                                autoSelectOccurrence={
                                                    autoSelectOccurrence
                                                }
                                            />
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                ) : null}
                {roster &&
                ((canManage && roster.mode === 'open_interest') ||
                    (roster.mode !== 'open_interest' &&
                        roster.interests.length > 0)) ? (
                    <InterestSubmissions
                        roster={roster}
                        currentUserCid={currentUserCid}
                        occurrenceDate={occurrence.date}
                        autoSelectOccurrence={autoSelectOccurrence}
                    />
                ) : null}
                {roster?.mode === 'open_interest' ? (
                    <>
                        {canParticipate || ownInterest ? (
                            <RosterInterestForm
                                key={
                                    roster.id +
                                    '-' +
                                    occurrence.date +
                                    '-' +
                                    (ownInterest?.id ?? 'new')
                                }
                                roster={roster}
                                occurrence={occurrence}
                                interest={ownInterest}
                                canSubmit={canSignUp}
                                autoSelectOccurrence={autoSelectOccurrence}
                            />
                        ) : null}

                        {!canParticipate && !ownInterest ? (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Selected positions</CardTitle>
                                    <CardDescription>
                                        The coordinator is collecting controller
                                        interest for these positions.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-wrap gap-2">
                                    {roster.positions.map((position) => (
                                        <Badge
                                            key={position.id}
                                            variant="outline"
                                        >
                                            {position.callsign}
                                        </Badge>
                                    ))}
                                </CardContent>
                            </Card>
                        ) : null}
                    </>
                ) : null}
            </div>
        </>
    );
}
