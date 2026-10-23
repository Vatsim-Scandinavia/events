import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    Check,
    ChevronDown,
    Clock,
    Copy,
    ExternalLink,
    FileText,
    Globe,
    Hourglass,
    Pencil,
    Repeat,
    Send,
    Route,
} from 'lucide-react';
import { useLayoutEffect, useRef, useState, useSyncExternalStore, type ReactNode } from 'react';
import { useConfirm } from '@/components/confirm-dialog';
import { AirportBadges, EventBanner, EventTypeBadge } from '@/components/event-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import EventStaffing from '@/components/staffing/event-staffing';
import { Card, CardAction, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout';
import { descriptionClasses } from '@/lib/description';
import { describeTimezone } from '@/lib/time';
import { cn } from '@/lib/utils';
import type { EventRoute, HubEventDetail } from '@/types/event';
import type { EventStaffing as EventStaffingProps } from '@/types/staffing';

type EventsShowProps = {
    event: HubEventDetail;
    staffing: EventStaffingProps;
    canEdit: boolean;
};

export default function EventsShow({ event, staffing, canEdit }: EventsShowProps) {
    const { auth, displayTimezone } = usePage().props;
    // The whole event, or the date shown (every remaining date is cancelled).
    const cancelled = event.cancelled || staffing.occurrence.cancelled;

    return (
        <>
            <Head title={event.name} />

            <section className="border-b bg-muted/40">
                <div className="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-8 sm:px-6 md:py-12">
                    <div className="flex items-center justify-between gap-2">
                        <Button variant="ghost" size="sm" className="-ml-2.5" asChild>
                            <Link href="/events">
                                <ArrowLeft />
                                All events
                            </Link>
                        </Button>
                        {canEdit && (
                            <div className="flex items-center gap-2">
                                {event.draft && <PublishButton event={event} />}
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={`/events/${event.id}/edit`}>
                                        <Pencil />
                                        Edit event
                                    </Link>
                                </Button>
                            </div>
                        )}
                    </div>

                    <div className="overflow-hidden rounded-xl ring-1 ring-foreground/10">
                        <EventBanner event={event} className="aspect-[2/1] sm:aspect-[3/1]" />
                    </div>

                    <div className="flex flex-col items-start gap-3">
                        <div className="flex items-center gap-2">
                            <EventTypeBadge type={event.type} />
                            {event.draft && (
                                <Badge variant="outline" title="Only staff who can view or manage this FIR’s events can see it until it’s published.">
                                    Draft
                                </Badge>
                            )}
                            {cancelled && <Badge variant="destructive">Cancelled</Badge>}
                            {event.fir && (
                                <span className="text-sm text-muted-foreground">
                                    Run by {event.fir.name !== event.fir.icao ? `${event.fir.name} ` : ''}
                                    <span className="font-mono">{event.fir.icao}</span>
                                </span>
                            )}
                        </div>
                        <h1 className="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">{event.name}</h1>
                        <AirportBadges airports={event.airports} />
                    </div>
                </div>
            </section>

            <div className="mx-auto grid max-w-6xl items-start gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[1fr_320px]">
                <div className="flex flex-col gap-10">
                    <section aria-labelledby="about">
                        <SectionHeading id="about">About this event</SectionHeading>
                        {event.description ? (
                            <Description html={event.description} />
                        ) : (
                            <p className="text-muted-foreground">More details will be published closer to the event.</p>
                        )}
                    </section>

                    {event.routes.length > 0 && (
                        <section aria-labelledby="routes">
                            <SectionHeading id="routes" icon={<Route className="size-5" />}>
                                Suggested routes
                            </SectionHeading>
                            <div className="flex flex-col gap-3">
                                {event.routes.map((route) => (
                                    <RouteRow key={`${route.from}-${route.to}-${route.route}`} route={route} />
                                ))}
                            </div>
                        </section>
                    )}

                    {!cancelled && staffing.mode !== 'none' && <EventStaffing eventId={event.id} staffing={staffing} />}

                    {!cancelled && staffing.mode === 'none' && staffing.canManage && (
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl px-4 py-3 text-sm ring-1 ring-foreground/10">
                            <span className="text-muted-foreground">Controllers can't staff this event yet.</span>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={`/events/${event.id}/staffing`}>Set up staffing</Link>
                            </Button>
                        </div>
                    )}
                </div>

                {/* First on small screens, so the date and booking aren't below everything else. */}
                <Card className="order-first lg:sticky lg:top-24 lg:order-none">
                    <CardHeader>
                        <CardTitle>When</CardTitle>
                        {!cancelled && (
                            <CardAction>
                                <StartsIn startsAt={event.startsAt} endsAt={event.endsAt} />
                            </CardAction>
                        )}
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3 text-sm text-muted-foreground">
                        <span className={cn('flex items-center gap-2', cancelled && 'line-through')}>
                            <CalendarDays className="size-4" />
                            {staffing.occurrence.date}
                        </span>
                        <span className={cn('flex items-center gap-2', cancelled && 'line-through')}>
                            <Clock className="size-4" />
                            {staffing.occurrence.time}
                        </span>
                        {!cancelled && displayTimezone !== 'UTC' && (
                            <span className="flex items-center gap-2">
                                <Globe className="size-4" />
                                {event.zuluTime}
                            </span>
                        )}
                        {event.duration && (
                            <span className="flex items-center gap-2">
                                <Hourglass className="size-4" />
                                {event.duration}
                            </span>
                        )}
                        {event.repeats && (
                            <span className="flex items-center gap-2">
                                <Repeat className="size-4" />
                                {event.repeats}
                            </span>
                        )}
                        {!event.cancelled && <OtherDates staffing={staffing} />}
                        <p className="text-xs">
                            All times are in {describeTimezone(displayTimezone)}.{' '}
                            {auth.user && (
                                <Link href="/settings" className="underline underline-offset-2 hover:text-foreground">
                                    Change
                                </Link>
                            )}
                        </p>
                    </CardContent>
                    <CardFooter className="flex flex-col gap-2">
                        {cancelled ? (
                            <p className="w-full rounded-lg bg-destructive/10 px-3 py-2 text-center text-sm text-destructive">
                                {event.cancelled ? 'This event has been cancelled.' : 'This date has been cancelled.'}
                            </p>
                        ) : event.hasEnded ? (
                            <p className="w-full rounded-lg bg-muted px-3 py-2 text-center text-sm text-muted-foreground">This event has finished.</p>
                        ) : (
                            event.bookingUrl && (
                                <Button className="w-full" asChild>
                                    <a href={event.bookingUrl} target="_blank" rel="noreferrer">
                                        Reserve a slot
                                        <ExternalLink />
                                    </a>
                                </Button>
                            )
                        )}
                        {event.briefingUrl && (
                            <Button variant="outline" className="w-full" asChild>
                                <a href={event.briefingUrl} target="_blank" rel="noreferrer">
                                    <FileText />
                                    Read the briefing
                                </a>
                            </Button>
                        )}
                        {!cancelled && !event.hasEnded && !event.bookingUrl && (
                            <p className="text-center text-xs text-muted-foreground">No slot needed. Just show up and fly.</p>
                        )}
                    </CardFooter>
                </Card>
            </div>
        </>
    );
}

/**
 * Publishes a Draft after asking, since it can't be taken back.
 */
function PublishButton({ event }: { event: HubEventDetail }) {
    const confirm = useConfirm();

    const publish = async () => {
        const confirmed = await confirm({
            title: `Publish ${event.name}?`,
            description: 'Everyone will be able to see it on the events list. Published events can’t go back to being a Draft.',
            confirmLabel: 'Publish',
        });

        if (confirmed) {
            router.post(`/events/${event.id}/publish`, {}, { preserveScroll: true });
        }
    };

    return (
        <Button size="sm" onClick={publish}>
            <Send />
            Publish
        </Button>
    );
}

EventsShow.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;

const MINUTE = 60_000;

const everyMinute = (tick: () => void) => {
    const timer = setInterval(tick, MINUTE);

    return () => clearInterval(timer);
};

// The viewer's clock to the minute. Null when rendered on the server, which can't know the
// viewer's time zone or clock.
function useNow(): Date | null {
    const minute = useSyncExternalStore(
        everyMinute,
        () => Math.floor(Date.now() / MINUTE),
        () => null,
    );

    return minute === null ? null : new Date(minute * MINUTE);
}

const startsIn = new Intl.RelativeTimeFormat('en', { numeric: 'always' });

function StartsIn({ startsAt, endsAt }: { startsAt: string; endsAt: string }) {
    const now = useNow();

    if (!now || now >= new Date(endsAt)) {
        return null;
    }

    const minutes = Math.round((new Date(startsAt).getTime() - now.getTime()) / MINUTE);

    if (minutes <= 0) {
        return (
            <span className="inline-flex items-center gap-1.5 text-xs font-medium text-success">
                <span className="size-2 rounded-full bg-success motion-safe:animate-pulse" aria-hidden="true" />
                Happening now
            </span>
        );
    }

    const label =
        minutes < 60
            ? startsIn.format(minutes, 'minute')
            : minutes < 48 * 60
              ? startsIn.format(Math.round(minutes / 60), 'hour')
              : startsIn.format(Math.round(minutes / (24 * 60)), 'day');

    return <span className="text-xs font-medium text-muted-foreground">Starts {label}</span>;
}

const OTHER_DATES_LIMIT = 3;

// The event's other upcoming dates, for events that run more than once.
function OtherDates({ staffing }: { staffing: EventStaffingProps }) {
    // Events that repeat every week have many dates; the next few are enough.
    const shown = staffing.occurrences
        .filter((occurrence) => !occurrence.hasEnded && occurrence.id !== staffing.occurrence.id)
        .slice(0, OTHER_DATES_LIMIT);

    if (shown.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-1 border-t pt-3">
            <span className="text-xs font-medium tracking-wide uppercase">Also on</span>
            {shown.map((occurrence) => (
                <span key={occurrence.id}>
                    <span className={cn(occurrence.cancelled && 'line-through')}>
                        {occurrence.date} · {occurrence.time}
                    </span>
                    {occurrence.cancelled && <span className="text-destructive"> · Cancelled</span>}
                </span>
            ))}
        </div>
    );
}

function SectionHeading({ id, icon, children }: { id: string; icon?: ReactNode; children: ReactNode }) {
    return (
        <h2 id={id} className="mb-4 flex items-center gap-2 text-xl font-semibold tracking-tight">
            {icon && <span className="text-muted-foreground">{icon}</span>}
            {children}
        </h2>
    );
}

function RouteRow({ route }: { route: EventRoute }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(route.route);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // Clipboard access can be blocked; the route stays selectable either way.
        }
    };

    return (
        <div className="flex flex-col gap-2 rounded-xl bg-card p-4 ring-1 ring-foreground/10">
            <div className="flex items-center justify-between gap-2">
                <span className="flex flex-wrap items-center gap-2">
                    <span className="font-mono text-sm font-medium">
                        {route.from} → {route.to}
                    </span>
                    {route.mandatory && <Badge>Mandatory</Badge>}
                </span>
                <Button variant="ghost" size="sm" onClick={copy} aria-label={`Copy route from ${route.from} to ${route.to}`}>
                    {copied ? <Check /> : <Copy />}
                    {copied ? 'Copied' : 'Copy'}
                </Button>
            </div>
            <code className="rounded-md bg-muted px-3 py-2 font-mono text-xs leading-relaxed break-words text-muted-foreground">{route.route}</code>
        </div>
    );
}

// Long descriptions are clamped to a few lines, with a toggle to show the rest.
function Description({ html }: { html: string }) {
    const ref = useRef<HTMLDivElement>(null);
    const [expanded, setExpanded] = useState(false);
    const [overflows, setOverflows] = useState(false);

    useLayoutEffect(() => {
        const element = ref.current;

        if (!element || expanded) {
            return;
        }

        const measure = () => setOverflows(element.scrollHeight > element.clientHeight + 1);
        const observer = new ResizeObserver(measure);

        measure();
        observer.observe(element);

        return () => observer.disconnect();
    }, [html, expanded]);

    return (
        <div className="flex flex-col items-start gap-2">
            <div
                ref={ref}
                id="event-description"
                className={cn('w-full', descriptionClasses, !expanded && 'line-clamp-6')}
                // Sanitised on the server by App\Services\EventDescription.
                dangerouslySetInnerHTML={{ __html: html }}
            />
            {(overflows || expanded) && (
                <Button
                    variant="link"
                    size="sm"
                    className="h-auto px-0"
                    aria-expanded={expanded}
                    aria-controls="event-description"
                    onClick={() => setExpanded((value) => !value)}
                >
                    {expanded ? 'Show less' : 'Read more'}
                    <ChevronDown className={cn('transition-transform', expanded && 'rotate-180')} />
                </Button>
            )}
        </div>
    );
}
