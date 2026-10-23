import { Head, Link, usePage } from '@inertiajs/react';
import { CalendarDays, ChevronRight, Plus } from 'lucide-react';
import type { ReactNode } from 'react';
import { AirportBadges, EventTypeBadge } from '@/components/event-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout';
import { describeTimezone } from '@/lib/time';
import type { HubEvent } from '@/types/event';

type EventsIndexProps = {
    events: HubEvent[];
    canCreate: boolean;
};

export default function EventsIndex({ events, canCreate }: EventsIndexProps) {
    const { vacc, displayTimezone } = usePage().props;
    const months = groupByMonth(events);

    return (
        <>
            <Head title="Events" />

            <section className="border-b bg-muted/40">
                <div className="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-12 sm:px-6 md:py-16">
                    <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">Upcoming events</h1>
                    <p className="max-w-2xl text-lg text-muted-foreground">
                        Everything on the {vacc.name} calendar, soonest first. All times are in {describeTimezone(displayTimezone)}.
                    </p>
                    {canCreate && (
                        <Button className="mt-2 self-start" asChild>
                            <Link href="/events/create">
                                <Plus />
                                New event
                            </Link>
                        </Button>
                    )}
                </div>
            </section>

            <div className="mx-auto flex max-w-6xl flex-col gap-10 px-4 py-12 sm:px-6">
                {months.length > 0 ? (
                    months.map(([month, monthEvents]) => (
                        <section key={month} aria-labelledby={`month-${month}`}>
                            <h2
                                id={`month-${month}`}
                                className="mb-3 flex items-center gap-2 text-sm font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                <CalendarDays className="size-4" />
                                {month}
                                <span className="font-normal normal-case">
                                    · {monthEvents.length} {monthEvents.length === 1 ? 'event' : 'events'}
                                </span>
                            </h2>
                            <div className="overflow-hidden rounded-xl bg-card ring-1 ring-foreground/10">
                                <table className="w-full table-fixed text-sm">
                                    <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                                        <tr>
                                            <th scope="col" className="w-20 px-4 py-2.5 font-medium">
                                                Date
                                            </th>
                                            <th scope="col" className="px-4 py-2.5 font-medium">
                                                Event
                                            </th>
                                            <th scope="col" className="hidden w-72 px-4 py-2.5 font-medium md:table-cell">
                                                Airports
                                            </th>
                                            <th scope="col" className="hidden w-36 px-4 py-2.5 font-medium sm:table-cell">
                                                Time
                                            </th>
                                            <th scope="col" className="w-16 px-4 py-2.5 lg:w-32">
                                                <span className="sr-only">Actions</span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {monthEvents.map((event) => (
                                            <EventRow key={event.id} event={event} />
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    ))
                ) : (
                    <p className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                        No events are scheduled right now. Check back soon.
                    </p>
                )}
            </div>
        </>
    );
}

EventsIndex.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;

function EventRow({ event }: { event: HubEvent }) {
    return (
        <tr className="transition-colors hover:bg-muted/50">
            <td className="px-4 py-3 align-middle">
                <div className="flex w-12 flex-col items-center rounded-lg bg-muted py-1.5 leading-none">
                    <span className="text-lg font-semibold tabular-nums">{event.day}</span>
                    <span className="mt-1 text-xs text-muted-foreground uppercase">{event.weekday}</span>
                </div>
            </td>
            <td className="px-4 py-3 align-middle">
                <div className="flex flex-col items-start gap-1.5">
                    <span className="font-medium text-foreground">{event.name}</span>
                    <div className="flex flex-wrap items-center gap-1.5">
                        <EventTypeBadge type={event.type} />
                        {event.draft && <Badge variant="outline">Draft</Badge>}
                        <span className="font-mono text-xs text-muted-foreground" title={event.fir.name}>
                            {event.fir.icao}
                        </span>
                        <span className="text-xs text-muted-foreground tabular-nums sm:hidden">{event.time}</span>
                    </div>
                    <div className="md:hidden">
                        <AirportBadges airports={event.airports} />
                    </div>
                </div>
            </td>
            <td className="hidden px-4 py-3 align-middle md:table-cell">
                <AirportBadges airports={event.airports} />
            </td>
            <td className="hidden px-4 py-3 align-middle whitespace-nowrap text-muted-foreground tabular-nums sm:table-cell">{event.time}</td>
            <td className="px-4 py-3 text-right align-middle">
                <Button variant="ghost" size="sm" asChild>
                    <Link href={`/events/${event.id}`} aria-label={`View details for ${event.name}`}>
                        <span className="hidden lg:inline">Details</span>
                        <ChevronRight />
                    </Link>
                </Button>
            </td>
        </tr>
    );
}

// Events arrive sorted by start time, so grouping keeps months in order.
function groupByMonth(events: HubEvent[]): [string, HubEvent[]][] {
    const groups = new Map<string, HubEvent[]>();

    for (const event of events) {
        groups.set(event.month, [...(groups.get(event.month) ?? []), event]);
    }

    return [...groups.entries()];
}
