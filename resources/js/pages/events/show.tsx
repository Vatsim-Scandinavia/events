import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Ban,
    CalendarDays,
    ChevronRight,
    Pencil,
    Plus,
    RotateCcw,
    X,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { EventBanner } from '@/components/event-banner';
import { EventField } from '@/components/event-field';
import { EventPublicationControls } from '@/components/event-publication-controls';
import InputError from '@/components/input-error';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { eventTime, recurrenceLabel } from '@/lib/event-time';
import { edit, index, show } from '@/routes/events';
import {
    store as cancel,
    destroy as restore,
} from '@/routes/events/cancellations';
import {
    store as invite,
    destroy as revoke,
} from '@/routes/events/collaborations';
import { show as roster } from '@/routes/events/roster';
import type {
    Fir,
    EventDetails as EventDetailsData,
    Occurrence,
} from '@/types/events';

type Props = {
    event: EventDetailsData;
    occurrences: Occurrence[];
    from: string;
    next_from: string | null;
    collaborations?: { id: number; team: Fir; accepted: boolean }[];
    firs?: Fir[];
    can?: {
        edit: boolean;
        manage_owner: boolean;
        publish: boolean;
        unpublish: boolean;
    };
};

function CancellationDialog({
    event,
    date,
    onClose,
    returnFocus,
}: {
    event: EventDetailsData;
    date: string | null;
    onClose: () => void;
    returnFocus: () => void;
}) {
    const form = useForm({ occurrence_date: date, reason: '' });
    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open && !form.processing) onClose();
            }}
        >
            <DialogContent
                onCloseAutoFocus={(e) => {
                    e.preventDefault();
                    returnFocus();
                }}
            >
                <DialogHeader>
                    <DialogTitle>
                        {date
                            ? 'Cancel this occurrence'
                            : event.recurrence === 'none'
                              ? 'Cancel event'
                              : 'Cancel entire series'}
                    </DialogTitle>
                    <DialogDescription>
                        {date
                            ? 'Only the occurrence starting on ' +
                              date +
                              ' in ' +
                              event.timezone +
                              ' will be cancelled. The rest of the series will keep its schedule.'
                            : 'All occurrences of “' +
                              event.title +
                              '” will be marked as cancelled. Its details and cancellation history will be retained.'}
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="flex flex-col gap-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(cancel.url(event.id), {
                            preserveScroll: true,
                            onSuccess: () => {
                                toast.success(
                                    date
                                        ? 'Occurrence cancelled.'
                                        : 'Event cancelled.',
                                );
                                onClose();
                            },
                        });
                    }}
                >
                    <InputError message={form.errors.occurrence_date} />
                    <EventField
                        id="cancel-reason"
                        label="Reason (optional)"
                        error={form.errors.reason}
                    >
                        <Textarea
                            id="cancel-reason"
                            placeholder="For example, insufficient staffing"
                            value={form.data.reason}
                            onChange={(e) =>
                                form.setData('reason', e.target.value)
                            }
                            maxLength={2000}
                            disabled={form.processing}
                            aria-invalid={!!form.errors.reason}
                            aria-describedby="cancel-reason-error"
                        />
                    </EventField>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={form.processing}
                            onClick={onClose}
                        >
                            Keep event
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={form.processing}
                        >
                            {form.processing ? <Spinner /> : null}Confirm
                            cancellation
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function RestorationButton({
    event,
    date = null,
    onRestored,
}: {
    event: EventDetailsData;
    date?: string | null;
    onRestored: () => void;
}) {
    const form = useForm({ occurrence_date: date });
    const label = date
        ? 'Restore occurrence'
        : event.recurrence === 'none'
          ? 'Restore event'
          : 'Restore series';

    return (
        <div className="flex flex-col gap-2">
            <Button
                type="button"
                variant={date ? 'ghost' : 'default'}
                size={date ? 'sm' : 'default'}
                aria-label={date ? label + ' ' + date : label}
                disabled={form.processing}
                onClick={() =>
                    form.submit(restore(event.id), {
                        preserveScroll: true,
                        onSuccess: () => {
                            toast.success(
                                date
                                    ? 'Occurrence restored.'
                                    : 'Event restored to draft.',
                            );
                            onRestored();
                        },
                    })
                }
            >
                {form.processing ? (
                    <Spinner data-icon="inline-start" />
                ) : (
                    <RotateCcw data-icon="inline-start" />
                )}
                {date ? 'Restore' : label}
            </Button>
            <InputError message={form.errors.occurrence_date} />
        </div>
    );
}

export default function EventDetails({
    event,
    occurrences,
    from,
    next_from,
    collaborations,
    firs = [],
    can = {
        edit: false,
        manage_owner: false,
        publish: false,
        unpublish: false,
    },
}: Props) {
    const [displayZone, setDisplayZone] = useState('UTC');
    const [cancellation, setCancellation] = useState<{
        date: string | null;
    } | null>(null);
    const trigger = useRef<HTMLButtonElement | null>(null);
    const heading = useRef<HTMLHeadingElement | null>(null);
    const invitation = useForm({ team_id: '' });
    const dates = useForm({ from });
    return (
        <>
            <Head title={event.title} />
            <div className="flex flex-1 flex-col gap-6">
                <Link
                    href={index()}
                    className="text-muted-foreground flex w-fit items-center gap-2 text-sm hover:underline"
                >
                    <ArrowLeft className="size-4" />
                    All events
                </Link>
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex min-w-0 flex-col gap-3">
                        <div className="flex flex-wrap gap-2">
                            <Badge
                                variant={
                                    event.status === 'cancelled'
                                        ? 'destructive'
                                        : 'secondary'
                                }
                            >
                                {event.status === 'draft'
                                    ? 'Draft'
                                    : event.status === 'published'
                                      ? 'Published'
                                      : 'Cancelled'}
                            </Badge>
                            <Badge variant="outline">{event.owner.code}</Badge>
                            {event.roster_enabled === false ? (
                                <Badge variant="outline">No roster</Badge>
                            ) : null}
                        </div>
                        <h1
                            ref={heading}
                            tabIndex={-1}
                            className="text-2xl font-semibold tracking-tight"
                        >
                            {event.title}
                        </h1>
                        <div
                            className="event-markdown text-muted-foreground max-w-3xl"
                            dangerouslySetInnerHTML={{
                                __html: event.short_description_html,
                            }}
                        />
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <EventPublicationControls
                            event={event}
                            canPublish={can.publish}
                            canUnpublish={can.unpublish}
                        />
                        {event.roster_enabled &&
                        (can.edit || event.roster_exists) ? (
                            <Button asChild variant="outline">
                                <Link href={roster({ event: event.id })}>
                                    {can.edit ? 'Manage roster' : 'View roster'}
                                </Link>
                            </Button>
                        ) : null}
                        {can.edit ? (
                            <Button asChild variant="outline">
                                <Link href={edit(event.id)}>
                                    <Pencil data-icon="inline-start" />
                                    Edit event
                                </Link>
                            </Button>
                        ) : null}
                        {can.manage_owner && event.status !== 'cancelled' ? (
                            <Button
                                variant="destructive"
                                onClick={(e) => {
                                    trigger.current = e.currentTarget;
                                    setCancellation({ date: null });
                                }}
                            >
                                <Ban data-icon="inline-start" />
                                {event.recurrence === 'none'
                                    ? 'Cancel event'
                                    : 'Cancel series'}
                            </Button>
                        ) : null}
                        {can.manage_owner && event.status === 'cancelled' ? (
                            <RestorationButton
                                event={event}
                                onRestored={() =>
                                    heading.current?.focus({
                                        preventScroll: true,
                                    })
                                }
                            />
                        ) : null}
                    </div>
                </header>
                {event.status === 'draft' ? (
                    <p className="text-muted-foreground text-sm">
                        This draft is private to the owner FIR and accepted
                        collaborators.
                    </p>
                ) : null}
                {event.status === 'cancelled' ? (
                    <Alert>
                        <AlertTitle>Event cancelled</AlertTitle>
                        <AlertDescription>
                            {event.cancellation_reason ||
                                'All occurrences have been cancelled.'}
                            {can.manage_owner ? (
                                <p>
                                    Restoring returns this event to draft. Any
                                    individually cancelled occurrences will stay
                                    cancelled until restored separately.
                                </p>
                            ) : null}
                        </AlertDescription>
                    </Alert>
                ) : null}
                <div className="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
                    <div className="flex min-w-0 flex-col gap-6">
                        <Card className="overflow-hidden pt-0">
                            <EventBanner src={event.banner_url} />
                            <CardHeader>
                                <CardTitle>About this event</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div
                                    className="event-markdown"
                                    dangerouslySetInnerHTML={{
                                        __html: event.description_html,
                                    }}
                                />
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Occurrences</CardTitle>
                                <CardDescription>
                                    {recurrenceLabel(event)} · Scheduled in{' '}
                                    {event.timezone}
                                    {event.recurrence_until
                                        ? ' · Through ' + event.recurrence_until
                                        : ''}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-5">
                                {event.timezone !== 'UTC' ? (
                                    <div className="flex flex-wrap items-center gap-3">
                                        <span className="text-muted-foreground text-sm">
                                            Display times in
                                        </span>
                                        <ToggleGroup
                                            type="single"
                                            value={displayZone}
                                            onValueChange={(value) => {
                                                if (value)
                                                    setDisplayZone(value);
                                            }}
                                            variant="outline"
                                            size="sm"
                                        >
                                            <ToggleGroupItem value="UTC">
                                                UTC / Zulu
                                            </ToggleGroupItem>
                                            <ToggleGroupItem
                                                value={event.timezone}
                                            >
                                                {event.timezone}
                                            </ToggleGroupItem>
                                        </ToggleGroup>
                                    </div>
                                ) : (
                                    <p className="text-muted-foreground text-sm">
                                        Times in UTC / Zulu
                                    </p>
                                )}
                                <form
                                    className="flex flex-wrap items-end gap-3"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        dates.get(show.url(event.id), {
                                            preserveState: 'errors',
                                            preserveScroll: true,
                                        });
                                    }}
                                >
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="occurrences-from">
                                            Starting from (schedule timezone)
                                        </Label>
                                        <Input
                                            id="occurrences-from"
                                            type="date"
                                            value={dates.data.from}
                                            onChange={(e) =>
                                                dates.setData(
                                                    'from',
                                                    e.target.value,
                                                )
                                            }
                                            required
                                            disabled={dates.processing}
                                            aria-invalid={!!dates.errors.from}
                                            aria-describedby="occurrences-from-error"
                                        />
                                        <InputError
                                            id="occurrences-from-error"
                                            message={dates.errors.from}
                                        />
                                    </div>
                                    <Button
                                        variant="outline"
                                        disabled={dates.processing}
                                    >
                                        Show dates
                                    </Button>
                                </form>
                                <ul className="divide-y">
                                    {occurrences.map((occurrence) => (
                                        <li
                                            key={occurrence.date}
                                            className="flex flex-wrap items-center justify-between gap-3 py-4"
                                        >
                                            <div className="flex min-w-0 items-start gap-3">
                                                <CalendarDays className="text-muted-foreground mt-0.5 size-4 shrink-0" />
                                                <div className="flex flex-col gap-1">
                                                    <p className="text-sm font-medium">
                                                        {occurrence.starts_at
                                                            ? eventTime(
                                                                  occurrence.starts_at,
                                                                  displayZone,
                                                              ) +
                                                              (displayZone ===
                                                              'UTC'
                                                                  ? ' Z'
                                                                  : '')
                                                            : occurrence.date +
                                                              ' · ' +
                                                              event.timezone}
                                                    </p>
                                                    {occurrence.ends_at ? (
                                                        <p className="text-muted-foreground text-xs">
                                                            Ends{' '}
                                                            {eventTime(
                                                                occurrence.ends_at,
                                                                displayZone,
                                                            )}
                                                            {displayZone ===
                                                            'UTC'
                                                                ? ' Z'
                                                                : ''}
                                                        </p>
                                                    ) : null}
                                                    {occurrence.reason ? (
                                                        <p className="text-muted-foreground max-w-lg text-sm">
                                                            {occurrence.reason}
                                                        </p>
                                                    ) : null}
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                {event.roster_enabled &&
                                                event.roster_exists ? (
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="sm"
                                                    >
                                                        <Link
                                                            href={roster({
                                                                event: event.id,
                                                                date: occurrence.date,
                                                            })}
                                                        >
                                                            View roster
                                                        </Link>
                                                    </Button>
                                                ) : null}
                                                <Badge
                                                    variant={
                                                        occurrence.status ===
                                                        'cancelled'
                                                            ? 'destructive'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {occurrence.status}
                                                </Badge>
                                                {can.edit &&
                                                occurrence.status ===
                                                    'scheduled' ? (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        aria-label={
                                                            'Cancel occurrence ' +
                                                            occurrence.date
                                                        }
                                                        onClick={(e) => {
                                                            trigger.current =
                                                                e.currentTarget;
                                                            setCancellation({
                                                                date: occurrence.date,
                                                            });
                                                        }}
                                                    >
                                                        Cancel
                                                    </Button>
                                                ) : null}
                                                {can.edit &&
                                                occurrence.status ===
                                                    'cancelled' ? (
                                                    <RestorationButton
                                                        event={event}
                                                        date={occurrence.date}
                                                        onRestored={() =>
                                                            heading.current?.focus(
                                                                {
                                                                    preventScroll: true,
                                                                },
                                                            )
                                                        }
                                                    />
                                                ) : null}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                                {!occurrences.length ? (
                                    <p className="text-muted-foreground py-4 text-sm">
                                        No occurrences on or after this date.
                                        Choose an earlier date to view past
                                        occurrences.
                                    </p>
                                ) : null}
                                {next_from ? (
                                    <Button asChild variant="outline">
                                        <Link
                                            href={show(event.id, {
                                                query: { from: next_from },
                                            })}
                                            preserveScroll
                                        >
                                            Later occurrences
                                            <ChevronRight />
                                        </Link>
                                    </Button>
                                ) : null}
                            </CardContent>
                        </Card>
                    </div>
                    <div className="flex min-w-0 flex-col gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Participating airports</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {!event.airports.length ? (
                                    <p className="text-muted-foreground text-sm">
                                        No airports specified.
                                    </p>
                                ) : null}
                                <ul className="flex flex-col gap-4">
                                    {event.airports.map((airport) => (
                                        <li
                                            key={airport.id}
                                            className="flex flex-col gap-1"
                                        >
                                            <span className="font-mono font-semibold">
                                                {airport.icao}
                                            </span>
                                            <span className="text-sm">
                                                {airport.name}
                                            </span>
                                            <span className="text-muted-foreground text-xs">
                                                {airport.country}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Hosted by</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="font-medium">
                                    {event.owner.code} · {event.owner.name}
                                </p>
                            </CardContent>
                        </Card>
                        {collaborations ? (
                            <Card>
                                <CardHeader>
                                    <CardTitle>FIR access</CardTitle>
                                    <CardDescription>
                                        Event Coordinators can edit. vACC Staff
                                        have read-only access.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-5">
                                    <ul className="flex flex-col gap-3">
                                        {collaborations.map((collaboration) => (
                                            <li
                                                key={collaboration.id}
                                                className="flex items-center justify-between gap-3"
                                            >
                                                <div>
                                                    <p className="text-sm">
                                                        {
                                                            collaboration.team
                                                                .code
                                                        }{' '}
                                                        ·{' '}
                                                        {
                                                            collaboration.team
                                                                .name
                                                        }
                                                    </p>
                                                    <p className="text-muted-foreground text-xs">
                                                        {collaboration.accepted
                                                            ? 'Collaborator'
                                                            : 'Invitation pending'}
                                                    </p>
                                                </div>
                                                {can.manage_owner ? (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={
                                                            'Remove access for ' +
                                                            collaboration.team
                                                                .code
                                                        }
                                                        onClick={() =>
                                                            router.delete(
                                                                revoke.url({
                                                                    event: event.id,
                                                                    collaboration:
                                                                        collaboration.id,
                                                                }),
                                                                {
                                                                    preserveScroll: true,
                                                                    onSuccess:
                                                                        () =>
                                                                            toast.success(
                                                                                'FIR access removed.',
                                                                            ),
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <X />
                                                    </Button>
                                                ) : null}
                                            </li>
                                        ))}
                                    </ul>
                                    {can.manage_owner && firs.length > 0 ? (
                                        <form
                                            className="flex flex-col gap-3"
                                            onSubmit={(e) => {
                                                e.preventDefault();
                                                invitation.post(
                                                    invite.url(event.id),
                                                    {
                                                        preserveScroll: true,
                                                        onSuccess: () => {
                                                            invitation.reset();
                                                            toast.success(
                                                                'Collaboration invitation created.',
                                                            );
                                                        },
                                                    },
                                                );
                                            }}
                                        >
                                            <Label htmlFor="invite-fir">
                                                Invite an FIR
                                            </Label>
                                            <Select
                                                value={invitation.data.team_id}
                                                onValueChange={(value) =>
                                                    invitation.setData(
                                                        'team_id',
                                                        value,
                                                    )
                                                }
                                                disabled={invitation.processing}
                                            >
                                                <SelectTrigger
                                                    id="invite-fir"
                                                    className="w-full"
                                                >
                                                    <SelectValue placeholder="Choose FIR" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        {firs.map((fir) => (
                                                            <SelectItem
                                                                key={fir.id}
                                                                value={String(
                                                                    fir.id,
                                                                )}
                                                            >
                                                                {fir.code} ·{' '}
                                                                {fir.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={
                                                    invitation.errors.team_id
                                                }
                                            />
                                            <Button
                                                variant="outline"
                                                disabled={
                                                    invitation.processing ||
                                                    !invitation.data.team_id
                                                }
                                            >
                                                <Plus data-icon="inline-start" />
                                                Invite FIR
                                            </Button>
                                            <p className="text-muted-foreground text-xs">
                                                A coordinator of the invited FIR
                                                must accept before its members
                                                gain staff access to the event.
                                            </p>
                                        </form>
                                    ) : null}
                                </CardContent>
                            </Card>
                        ) : null}
                    </div>
                </div>
            </div>
            {cancellation ? (
                <CancellationDialog
                    event={event}
                    date={cancellation.date}
                    onClose={() => setCancellation(null)}
                    returnFocus={() =>
                        (trigger.current?.isConnected
                            ? trigger.current
                            : heading.current
                        )?.focus()
                    }
                />
            ) : null}
        </>
    );
}
