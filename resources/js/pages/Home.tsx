import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, ExternalLink } from 'lucide-react';
import { Fragment, type ReactNode } from 'react';
import EventCard from '@/components/event-card';
import NetworkStats, { type NetworkStatsData } from '@/components/network-stats';
import SectionHeader from '@/components/section-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout';
import type { HubEvent } from '@/types/event';

type HomeProps = {
    upcomingEvents: HubEvent[];
    networkStats?: NetworkStatsData | null;
};

export default function Home({ upcomingEvents, networkStats }: HomeProps) {
    const { vacc } = usePage().props;
    // The first event is featured in the hero on wide screens, so there the grid starts after it.
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

                    {/* Only beside the text on wide screens; below lg the next event leads the upcoming grid instead. */}
                    {nextEvent && <EventCard event={nextEvent} featured className="hidden lg:flex" />}
                </div>
            </section>

            <section className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                <SectionHeader
                    title="Upcoming events"
                    description="The next events on the calendar."
                    action={
                        // Pulled out by the button's own padding, so its label lines up with the content edge.
                        <Button variant="ghost" className="-ml-2.5 sm:-mr-2.5 sm:ml-0" asChild>
                            <Link href="/events">
                                View all
                                <ArrowRight />
                            </Link>
                        </Button>
                    }
                />

                {nextEvent ? (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <EventCard event={nextEvent} className="lg:hidden" />
                            {laterEvents.map((event) => (
                                <EventCard key={event.id} event={event} />
                            ))}
                        </div>
                        {laterEvents.length === 0 && (
                            <p className="hidden rounded-xl border border-dashed p-8 text-center text-muted-foreground lg:block">
                                Nothing else is scheduled after the next event yet.
                            </p>
                        )}
                    </>
                ) : (
                    <p className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                        No events are scheduled right now. Check back soon.
                    </p>
                )}
            </section>

            <NetworkStats stats={networkStats} />
        </>
    );
}

Home.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
