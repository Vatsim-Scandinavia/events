import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowDown, Calendar, Plane } from 'lucide-react';
import { SiteHeader } from '@/components/site-header';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
    EmptyDescription,
} from '@/components/ui/empty';
import { Separator } from '@/components/ui/separator';
import { home } from '@/routes';

export default function Welcome() {
    const { name } = usePage().props;

    return (
        <div className="min-h-screen">
            <Head title="VATSIM events">
                <meta
                    name="description"
                    content="A shared home for VATSIM events. Find your next flight and be part of the activity."
                />
            </Head>
            <a href="#main-content" className="site-skip-link">
                Skip to content
            </a>
            <div className="site-container">
                <SiteHeader />
                <main id="main-content">
                    <section
                        className="landing-hero"
                        aria-labelledby="hero-heading"
                    >
                        <div>
                            <h1 id="hero-heading">
                                Good flights
                                <br />
                                start here.
                            </h1>
                            <p>
                                A shared home for VATSIM events.
                                <br className="hidden sm:block" /> Find your
                                next flight and be part of the activity.
                            </p>
                            <Button asChild size="lg">
                                <a href="#upcoming-events">
                                    Explore events{' '}
                                    <ArrowDown
                                        aria-hidden="true"
                                        data-icon="inline-end"
                                    />
                                </a>
                            </Button>
                        </div>
                        <img
                            className="landing-hero-image"
                            src="/images/events-coast.png"
                            alt="A view across coastal fjords and mountains from an aircraft window"
                            width="1200"
                            height="704"
                            fetchPriority="high"
                        />
                    </section>
                    <section
                        id="upcoming-events"
                        className="landing-events"
                        aria-labelledby="events-heading"
                    >
                        <div className="events-section-heading">
                            <h2 id="events-heading">Upcoming events</h2>
                            <p>All times in UTC</p>
                        </div>
                        <Empty className="min-h-54 border">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <Calendar aria-hidden="true" />
                                </EmptyMedia>
                                <EmptyTitle>
                                    Your next flight is on the horizon.
                                </EmptyTitle>
                                <EmptyDescription>
                                    There are no events to show yet. Check back
                                    for upcoming activity.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    </section>
                    <section
                        id="about"
                        className="landing-about"
                        aria-labelledby="about-heading"
                    >
                        <h2 id="about-heading">One community. One place.</h2>
                        <p>
                            For pilots and controllers coming together on the
                            network.
                        </p>
                    </section>
                </main>
                <Separator />
                <footer className="site-footer">
                    <Link href={home()} className="site-brand">
                        <Plane aria-hidden="true" className="size-6" />
                        <span>{name}</span>
                    </Link>
                    <p>Made for the VATSIM community</p>
                </footer>
            </div>
        </div>
    );
}
