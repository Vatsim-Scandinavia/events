import { Link } from '@inertiajs/react';
import { CalendarDays, Clock, Plane } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { HubEvent } from '@/types/event';

const maxVisibleAirports = 4;

export default function EventCard({ event }: { event: HubEvent }) {
    return (
        <Card className="pt-0 transition-all duration-200 hover:shadow-lg hover:shadow-black/30 hover:ring-foreground/25 motion-safe:hover:-translate-y-1">
            <EventBanner event={event} label={event.type} />
            <CardHeader>
                <CardTitle className="text-lg">{event.name}</CardTitle>
                <AirportBadges airports={event.airports} />
            </CardHeader>
            <CardContent className="flex flex-col gap-2 text-sm text-muted-foreground">
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
                <Button variant="outline" className="w-full" asChild>
                    <Link href={`/events/${event.id}`}>View details</Link>
                </Button>
            </CardFooter>
        </Card>
    );
}

export function EventBanner({ event, label, className }: { event: HubEvent; label?: string; className?: string }) {
    return (
        <div className={cn('relative aspect-video w-full overflow-hidden', className)}>
            {event.banner ? (
                <img
                    src={event.banner}
                    alt={`${event.name} banner`}
                    className="size-full object-cover transition-transform duration-300 motion-safe:group-hover/card:scale-105"
                />
            ) : (
                <div aria-hidden="true" className="flex size-full items-center justify-center bg-(image:--banner) text-muted-foreground">
                    <Plane className="size-10 opacity-40 transition-all duration-300 group-hover/card:opacity-70 motion-safe:group-hover/card:translate-x-1 motion-safe:group-hover/card:-translate-y-1" />
                </div>
            )}
            {label && (
                <span className="absolute top-3 left-3 rounded-md bg-background/70 px-2 py-1 text-xs font-medium text-foreground ring-1 ring-foreground/10 backdrop-blur">
                    {label}
                </span>
            )}
        </div>
    );
}

const typeColors = [
    'bg-sky-500/15 text-sky-700 ring-sky-500/30 dark:text-sky-300',
    'bg-amber-500/15 text-amber-700 ring-amber-500/30 dark:text-amber-300',
    'bg-emerald-500/15 text-emerald-700 ring-emerald-500/30 dark:text-emerald-300',
    'bg-violet-500/15 text-violet-700 ring-violet-500/30 dark:text-violet-300',
    'bg-rose-500/15 text-rose-700 ring-rose-500/30 dark:text-rose-300',
    'bg-teal-500/15 text-teal-700 ring-teal-500/30 dark:text-teal-300',
];

const knownTypeColors: Record<string, string> = {
    regional: typeColors[0],
    'special event': typeColors[1],
    training: typeColors[2],
    'fly-in': typeColors[3],
    'group flight': typeColors[4],
};

// Known types get a fixed colour; any other type gets one derived from its name, so it stays the same everywhere.
function typeColor(type: string): string {
    const known = knownTypeColors[type.toLowerCase()];

    if (known) {
        return known;
    }

    let hash = 0;

    for (const char of type.toLowerCase()) {
        hash = (hash * 31 + char.charCodeAt(0)) >>> 0;
    }

    return typeColors[hash % typeColors.length];
}

export function EventTypeBadge({ type }: { type: string }) {
    return (
        <span className={cn('inline-flex w-fit items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset', typeColor(type))}>
            {type}
        </span>
    );
}

export function AirportBadges({ airports }: { airports: string[] }) {
    const visible = airports.slice(0, maxVisibleAirports);
    const hidden = airports.slice(maxVisibleAirports);

    return (
        <div className="flex flex-wrap gap-1.5">
            {visible.map((icao) => (
                <Badge key={icao} variant="secondary" className="font-mono">
                    {icao}
                </Badge>
            ))}
            {hidden.length > 0 && (
                <Badge variant="outline" title={hidden.join(', ')}>
                    +{hidden.length}
                </Badge>
            )}
        </div>
    );
}
