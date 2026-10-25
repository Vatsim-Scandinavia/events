import { Deferred, usePoll } from '@inertiajs/react';
import { Headset, Plane, PlaneLanding, PlaneTakeoff, Users } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

export type NetworkStatsData = {
    pilots: number;
    controllers: number;
    users: number;
    busiestAirports: { icao: string; departures: number; arrivals: number }[];
    updatedAt: string;
};

// The server caches the feed for a minute, so polling faster gains nothing.
const pollInterval = 60_000;

const formatNumber = (value: number) => value.toLocaleString('en-US');

// Formatted by hand in UTC so the server and every client render the same string.
const formatZulu = (iso: string) => {
    const date = new Date(iso);

    return `${String(date.getUTCHours()).padStart(2, '0')}:${String(date.getUTCMinutes()).padStart(2, '0')}z`;
};

export default function NetworkStats({ stats }: { stats?: NetworkStatsData | null }) {
    usePoll(pollInterval, { only: ['networkStats'] });

    return (
        <section className="border-t bg-muted/40">
            <div className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                <div className="mb-8 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 className="flex items-center gap-3 text-2xl font-semibold tracking-tight">
                            <span className="relative flex size-2.5">
                                <span className="absolute inline-flex size-full animate-ping rounded-full bg-success opacity-75 motion-reduce:hidden" />
                                <span className="relative inline-flex size-2.5 rounded-full bg-success" />
                            </span>
                            Live on the network
                        </h2>
                        <p className="text-muted-foreground">Who is flying and controlling on VATSIM right now.</p>
                    </div>
                    {stats && <p className="text-sm text-muted-foreground">Updated {formatZulu(stats.updatedAt)}</p>}
                </div>

                <Deferred data="networkStats" fallback={<NetworkStatsSkeleton />}>
                    {stats ? <NetworkStatsContent stats={stats} /> : <NetworkStatsUnavailable />}
                </Deferred>
            </div>
        </section>
    );
}

function NetworkStatsContent({ stats }: { stats: NetworkStatsData }) {
    const busiestMovements = Math.max(1, ...stats.busiestAirports.map((airport) => airport.departures + airport.arrivals));

    return (
        <div className="grid gap-4 lg:grid-cols-3">
            <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-1">
                <StatTile icon={Plane} label="Pilots online" value={stats.pilots} />
                <StatTile icon={Headset} label="Controllers online" value={stats.controllers} />
                <StatTile icon={Users} label="Members connected" value={stats.users} />
            </div>

            <Card className="lg:col-span-2">
                <CardHeader>
                    <CardTitle>Busiest airports</CardTitle>
                </CardHeader>
                <CardContent>
                    <ol className="flex flex-col gap-4">
                        {stats.busiestAirports.map((airport) => {
                            const movements = airport.departures + airport.arrivals;

                            return (
                                <li key={airport.icao} className="flex flex-col gap-1.5">
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="font-mono font-medium">{airport.icao}</span>
                                        <span className="flex items-center gap-3 text-xs text-muted-foreground">
                                            <span className="flex items-center gap-1" title="Departures">
                                                <PlaneTakeoff className="size-3.5" />
                                                {formatNumber(airport.departures)}
                                            </span>
                                            <span className="flex items-center gap-1" title="Arrivals">
                                                <PlaneLanding className="size-3.5" />
                                                {formatNumber(airport.arrivals)}
                                            </span>
                                        </span>
                                    </div>
                                    <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                                        <div
                                            className="h-full rounded-full bg-primary transition-[width] duration-500"
                                            style={{ width: `${(movements / busiestMovements) * 100}%` }}
                                        />
                                    </div>
                                </li>
                            );
                        })}
                    </ol>
                </CardContent>
            </Card>
        </div>
    );
}

function StatTile({ icon: Icon, label, value }: { icon: LucideIcon; label: string; value: number }) {
    return (
        <Card>
            <CardContent className="flex items-center gap-4">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-icon">
                    <Icon className="size-5" />
                </span>
                <div>
                    <p className="text-3xl font-semibold tracking-tight tabular-nums">{formatNumber(value)}</p>
                    <p className="text-sm text-muted-foreground">{label}</p>
                </div>
            </CardContent>
        </Card>
    );
}

function NetworkStatsSkeleton() {
    return (
        <div className="grid gap-4 lg:grid-cols-3">
            <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-1">
                {Array.from({ length: 3 }, (_, index) => (
                    <Skeleton key={index} className="h-[88px] rounded-xl" />
                ))}
            </div>
            <Skeleton className="h-72 rounded-xl lg:col-span-2" />
        </div>
    );
}

function NetworkStatsUnavailable() {
    return (
        <Card>
            <CardContent className="text-sm text-muted-foreground">Live network data is unavailable right now. Check back in a minute.</CardContent>
        </Card>
    );
}
