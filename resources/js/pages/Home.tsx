import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, CalendarDays, Clock, ExternalLink, Headset } from 'lucide-react';
import type { ReactNode } from 'react';
import EventCard, { AirportBadges, EventBanner } from '@/components/event-card';
import NetworkStats, { type NetworkStatsData } from '@/components/network-stats';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout';
import { cn } from '@/lib/utils';
import type { HubEvent } from '@/types/event';

type HomeProps = {
    upcomingEvents: HubEvent[];
    networkStats?: NetworkStatsData | null;
};

export default function Home({ upcomingEvents, networkStats }: HomeProps) {
    const { vacc } = usePage().props;
    const { heroGradient } = vacc;
    const [nextEvent] = upcomingEvents;

    return (
        <>
            <Head title="Home" />

            <section className="relative overflow-hidden border-b bg-muted/40">
                <div
                    aria-hidden="true"
                    className={cn(
                        'pointer-events-none absolute inset-0',
                        !heroGradient &&
                            'bg-[radial-gradient(ellipse_at_top_right,color-mix(in_oklch,var(--color-primary)_10%,transparent),transparent_60%)]',
                    )}
                    // Mixed with transparent so the hero text stays readable in light and dark themes.
                    style={
                        heroGradient
                            ? {
                                  backgroundImage: `linear-gradient(135deg, color-mix(in oklch, ${heroGradient.from} 35%, transparent), color-mix(in oklch, ${heroGradient.to} 35%, transparent))`,
                              }
                            : undefined
                    }
                />
                <div className="relative mx-auto grid max-w-6xl items-center gap-12 px-4 py-20 sm:px-6 md:py-24 lg:grid-cols-[1fr_400px]">
                    <div className="flex flex-col items-start gap-6">
                        <Badge variant="secondary">
                            <Headset />
                            {vacc.division ? `${vacc.division} · ` : ''}Pilots and controllers
                        </Badge>
                        <h1 className="max-w-3xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                            Fly and control with {vacc.name}.
                        </h1>
                        <p className="max-w-2xl text-lg text-muted-foreground">
                            Fly-ins, controller events and group flights in our airspace, all in one place. Book your slot and get the briefing you
                            need before you push back or plug in.
                        </p>
                        {vacc.firs.length > 0 && (
                            <div className="flex flex-wrap items-center gap-1.5 text-sm text-muted-foreground">
                                <span className="mr-1">Our airspace</span>
                                {vacc.firs.map((fir) => (
                                    <Badge key={fir.icao} variant="outline" title={fir.name}>
                                        <span className="font-mono">{fir.icao}</span>
                                        {fir.name !== fir.icao && <span className="text-muted-foreground">{fir.name}</span>}
                                    </Badge>
                                ))}
                            </div>
                        )}
                        <div className="flex flex-wrap gap-3">
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
                    </div>

                    {nextEvent && <NextEventCard event={nextEvent} />}
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

                {upcomingEvents.length > 0 ? (
                    <div className="grid gap-4 md:grid-cols-3">
                        {upcomingEvents.map((event) => (
                            <EventCard key={event.id} event={event} />
                        ))}
                    </div>
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

function NextEventCard({ event }: { event: HubEvent }) {
    return (
        <Card className="pt-0 shadow-2xl shadow-black/40">
            <EventBanner event={event} />
            <CardHeader>
                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">Next event</p>
                <CardTitle className="text-xl">{event.name}</CardTitle>
                <AirportBadges airports={event.airports} />
            </CardHeader>
            <CardContent className="flex flex-wrap gap-x-4 gap-y-2 text-sm text-muted-foreground">
                <span className="flex items-center gap-2">
                    <CalendarDays className="size-4" />
                    {event.date}
                </span>
                <span className="flex items-center gap-2">
                    <Clock className="size-4" />
                    {event.time}
                </span>
            </CardContent>
            <CardFooter>
                <Button className="w-full" asChild>
                    <Link href={`/events/${event.id}`}>View details</Link>
                </Button>
            </CardFooter>
        </Card>
    );
}
