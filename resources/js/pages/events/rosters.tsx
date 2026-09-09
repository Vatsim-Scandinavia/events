import { Head, Link } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    ClipboardList,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { eventTime } from '@/lib/event-time';
import { show } from '@/routes/events/roster';
import { index } from '@/routes/rosters';
import type { Occurrence, Pagination } from '@/types/events';

type RosterSummary = {
    id: number;
    event_id: number;
    title: string;
    owner_code: string;
    mode: 'pre_slotted' | 'open_interest';
    is_open: boolean;
    has_ended: boolean;
    occurrence: Occurrence;
};

export default function Rosters({
    rosters,
}: {
    rosters: Pagination<RosterSummary>;
}) {
    return (
        <>
            <Head title="Rosters" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-col gap-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Rosters
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Book a position or share your interest and availability
                        for an event. All times are UTC / Zulu.
                    </p>
                </header>
                {rosters.data.length ? (
                    <div className="grid gap-5 md:grid-cols-2 2xl:grid-cols-3">
                        {rosters.data.map((roster) => (
                            <Card key={roster.id}>
                                <CardHeader>
                                    <div className="mb-2 flex flex-wrap gap-2">
                                        <Badge variant="outline">
                                            {roster.owner_code}
                                        </Badge>
                                        <Badge variant="secondary">
                                            {roster.mode === 'pre_slotted'
                                                ? 'Pre-slotted'
                                                : 'Open interest'}
                                        </Badge>
                                        <Badge
                                            variant={
                                                roster.occurrence.status ===
                                                'cancelled'
                                                    ? 'destructive'
                                                    : 'outline'
                                            }
                                        >
                                            {roster.occurrence.status ===
                                            'cancelled'
                                                ? 'Cancelled'
                                                : roster.has_ended
                                                  ? 'Ended'
                                                  : roster.is_open
                                                    ? 'Signups open'
                                                    : 'Signups closed'}
                                        </Badge>
                                    </div>
                                    <CardTitle>
                                        <Link
                                            href={show({
                                                event: roster.event_id,
                                                date: roster.occurrence.date,
                                            })}
                                        >
                                            {roster.title}
                                        </Link>
                                    </CardTitle>
                                    <CardDescription>
                                        {roster.mode === 'pre_slotted'
                                            ? 'Choose an available position and shift.'
                                            : 'Tell the coordinator where and when you can help.'}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex gap-2 text-sm">
                                        <CalendarDays className="mt-0.5 size-4 shrink-0" />
                                        <div>
                                            <p>
                                                {roster.occurrence.starts_at
                                                    ? eventTime(
                                                          roster.occurrence
                                                              .starts_at,
                                                      ) + ' Z'
                                                    : roster.occurrence.date}
                                            </p>
                                            {roster.occurrence.ends_at ? (
                                                <p className="text-muted-foreground">
                                                    Ends{' '}
                                                    {eventTime(
                                                        roster.occurrence
                                                            .ends_at,
                                                    )}{' '}
                                                    Z
                                                </p>
                                            ) : null}
                                        </div>
                                    </div>
                                </CardContent>
                                <CardFooter className="mt-auto">
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="w-full"
                                    >
                                        <Link
                                            href={show({
                                                event: roster.event_id,
                                                date: roster.occurrence.date,
                                            })}
                                        >
                                            View roster
                                        </Link>
                                    </Button>
                                </CardFooter>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed px-6 py-16 text-center">
                        <ClipboardList className="text-muted-foreground size-9" />
                        <h2 className="font-semibold">No rosters yet</h2>
                        <p className="text-muted-foreground max-w-md text-sm">
                            Rosters opened for controller signups will appear
                            here.
                        </p>
                    </div>
                )}
                {rosters.last_page > 1 ? (
                    <nav
                        aria-label="Roster pages"
                        className="flex items-center justify-between gap-3"
                    >
                        <span className="text-muted-foreground text-sm">
                            Page {rosters.current_page} of {rosters.last_page}
                        </span>
                        <div className="flex gap-2">
                            {rosters.current_page > 1 ? (
                                <Button asChild size="sm" variant="outline">
                                    <Link
                                        href={index({
                                            query: {
                                                page: rosters.current_page - 1,
                                            },
                                        })}
                                    >
                                        <ChevronLeft data-icon="inline-start" />
                                        Previous
                                    </Link>
                                </Button>
                            ) : null}
                            {rosters.current_page < rosters.last_page ? (
                                <Button asChild size="sm" variant="outline">
                                    <Link
                                        href={index({
                                            query: {
                                                page: rosters.current_page + 1,
                                            },
                                        })}
                                    >
                                        Next
                                        <ChevronRight data-icon="inline-end" />
                                    </Link>
                                </Button>
                            ) : null}
                        </div>
                    </nav>
                ) : null}
            </div>
        </>
    );
}
