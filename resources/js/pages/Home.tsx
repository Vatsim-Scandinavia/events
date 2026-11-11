import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, ExternalLink } from 'lucide-react';
import { Fragment, type ReactNode } from 'react';
import EventCard from '@/components/event-card';
import NetworkStats, { type NetworkStatsData } from '@/components/network-stats';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout';
import type { HubEvent } from '@/types/event';

type HomeProps = {
    upcomingEvents: HubEvent[];
    networkStats?: NetworkStatsData | null;
};

export default function Home({ upcomingEvents, networkStats }: HomeProps) {
    const { vacc } = usePage().props;
    // The first event is featured in the hero, so the grid starts after it.
    const [nextEvent, ...laterEvents] = upcomingEvents;

    return (
        <>
            <Head title="Home" />

            <section className="relative overflow-hidden border-b bg-muted/40">
                <div aria-hidden="true" className="pointer-events-none absolute inset-0 bg-(image:--hero)" />
                <div className="relative mx-auto grid max-w-6xl grid-cols-1 items-center gap-12 px-4 py-16 sm:px-6 sm:py-20 md:py-24 lg:grid-cols-[minmax(0,1fr)_400px]">
                    <div className="flex flex-col items-start gap-8">
                        <div className="flex flex-col gap-4">
                            <h1 className="max-w-3xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                                Fly and control with {vacc.name}.
                            </h1>
                            <p className="max-w-2xl text-lg text-muted-foreground">
                                Fly-ins, controller events and group flights in our airspace, all in one place. Book your slot and get the briefing
                                you need before you push back or plug in.
                            </p>
                        </div>
                        <div className="flex flex-col items-start gap-6">
                            <div className="flex flex-wrap gap-4">
                                <Button size="lg" asChild>
                                    <Link href="/events">
                                        Browse events
                                        <ArrowRight />
                                    </Link>
                                </Button>
                                {vacc.website && (
                                    <Button size="lg" variant="ghost" asChild>
                                        <a href={vacc.website} target="_blank" rel="noreferrer">
                                            {vacc.name} website
                                            <ExternalLink />
                                        </a>
                                    </Button>
                                )}
                            </div>
                            {vacc.firs.length > 0 && (
                                <p className="text-sm text-muted-foreground">
                                    Our airspace:{' '}
                                    {vacc.firs.map((fir, index) => (
                                        <Fragment key={fir.icao}>
                                            {index > 0 && ' · '}
                                            {/* A FIR's code and name stay on one line when the list wraps. */}
                                            <span className="whitespace-nowrap">
                                                <span className="font-mono">{fir.icao}</span>
                                                {fir.name !== fir.icao && ` ${fir.name}`}
                                            </span>
                                        </Fragment>
                                    ))}
                                </p>
                            )}
                        </div>
                    </div>

                    {nextEvent && <EventCard event={nextEvent} featured />}
                </div>
            </section>

            <section className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                <div className="mb-8 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 className="text-2xl font-semibold tracking-tight">Upcoming events</h2>
                        <p className="text-muted-foreground">The next events on the calendar.</p>
                    </div>
                    <Button variant="ghost" className="self-start sm:self-auto" asChild>
                        <Link href="/events">
                            View all
                            <ArrowRight />
                        </Link>
                    </Button>
                </div>

                {laterEvents.length > 0 ? (
                    <div className="grid gap-4 md:grid-cols-3">
                        {laterEvents.map((event) => (
                            <EventCard key={event.id} event={event} />
                        ))}
                    </div>
                ) : (
                    <p className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                        {nextEvent ? 'Nothing else is scheduled after the next event yet.' : 'No events are scheduled right now. Check back soon.'}
                    </p>
                )}
            </section>

            <NetworkStats stats={networkStats} />
        </>
    );
}

Home.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
