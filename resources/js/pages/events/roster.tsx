import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Pencil } from 'lucide-react';
import { useState } from 'react';
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
import { eventTime } from '@/lib/event-time';
import { show } from '@/routes/events';
import { index as rosters } from '@/routes/rosters';
import { destroy, store } from '@/routes/roster/bookings';
import type { EventRoster, RosterPageProps, RosterSlot } from '@/types/rosters';

function staffingTime(value: string) {
    return eventTime(value.length === 16 ? value + ':00Z' : value);
}

function BookingButton({
    rosterId,
    slot,
    ownBooking,
    canBook,
}: {
    rosterId: number;
    slot: RosterSlot;
    ownBooking: boolean;
    canBook: boolean;
}) {
    const form = useForm({});
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

function InterestSubmissions({ roster }: { roster: EventRoster }) {
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
                                        {interest.position_ids.map((id) => (
                                            <Badge key={id} variant="outline">
                                                {callsigns.get(id)}
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
                                          ? 'Event ended'
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
                            Edit roster
                        </Button>
                    ) : null}
                </header>
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
                                ? 'This event has ended'
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
                        event={event}
                        occurrence={occurrence}
                        roster={roster}
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
                                                            {staffingTime(
                                                                slot.starts_at,
                                                            )}{' '}
                                                            –{' '}
                                                            {staffingTime(
                                                                slot.ends_at,
                                                            )}{' '}
                                                            Z
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
                                                        rosterId={roster.id}
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
                {roster?.mode === 'open_interest' ? (
                    <>
                        {canParticipate || ownInterest ? (
                            <RosterInterestForm
                                key={
                                    roster.id + '-' + (ownInterest?.id ?? 'new')
                                }
                                roster={roster}
                                occurrence={occurrence}
                                interest={ownInterest}
                                canSubmit={canSignUp}
                            />
                        ) : null}
                        {canManage ? (
                            <InterestSubmissions roster={roster} />
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
