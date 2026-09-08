import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plane, Plus, Search } from 'lucide-react';
import { useRef, useState } from 'react';
import { AirportDialog } from '@/components/airport-dialog';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/airports';
import type { Airport, Pagination } from '@/types/events';

export default function Airports({
    airports,
    filters,
    can_create,
}: {
    airports: Pagination<Airport>;
    filters: { search: string };
    can_create: boolean;
}) {
    const form = useForm(filters);
    const [creating, setCreating] = useState(false);
    const trigger = useRef<HTMLButtonElement>(null);
    return (
        <>
            <Head title="Airports" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex flex-col gap-2">
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Airports
                            </h1>
                            <Badge variant="secondary">{airports.total}</Badge>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            The shared directory of participating airports.
                        </p>
                    </div>
                    {can_create ? (
                        <Button ref={trigger} onClick={() => setCreating(true)}>
                            <Plus data-icon="inline-start" />
                            Add airport
                        </Button>
                    ) : null}
                </header>
                <form
                    className="flex items-end gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.get(index.url(), { preserveState: false });
                    }}
                >
                    <div className="flex flex-1 flex-col gap-2">
                        <Label htmlFor="airport-search">Search airports</Label>
                        <Input
                            id="airport-search"
                            value={form.data.search}
                            onChange={(event) =>
                                form.setData('search', event.target.value)
                            }
                            placeholder="ICAO code, name or country"
                        />
                        <InputError message={form.errors.search} />
                    </div>
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={form.processing}
                    >
                        <Search data-icon="inline-start" />
                        Search
                    </Button>
                </form>
                {airports.data.length ? (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        ICAO
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Airport
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Country
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {airports.data.map((airport) => (
                                    <tr key={airport.id}>
                                        <td className="px-4 py-4 font-mono font-semibold">
                                            {airport.icao}
                                        </td>
                                        <td className="px-4 py-4">
                                            {airport.name}
                                        </td>
                                        <td className="px-4 py-4">
                                            {airport.country}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed px-6 py-16 text-center">
                        <Plane className="text-muted-foreground size-9" />
                        <h2 className="font-semibold">
                            {filters.search
                                ? 'No matching airports'
                                : 'No airports yet'}
                        </h2>
                        <p className="text-muted-foreground text-sm">
                            Airports added here or while planning an event
                            appear in this directory.
                        </p>
                    </div>
                )}
                {airports.last_page > 1 ? (
                    <nav
                        aria-label="Airport pages"
                        className="flex items-center justify-between"
                    >
                        <span className="text-muted-foreground text-sm">
                            Page {airports.current_page} of {airports.last_page}
                        </span>
                        <div className="flex gap-2">
                            {airports.current_page > 1 ? (
                                <Button asChild variant="outline" size="sm">
                                    <Link
                                        href={index({
                                            query: {
                                                ...filters,
                                                page: airports.current_page - 1,
                                            },
                                        })}
                                    >
                                        <ChevronLeft />
                                        Previous
                                    </Link>
                                </Button>
                            ) : null}
                            {airports.current_page < airports.last_page ? (
                                <Button asChild variant="outline" size="sm">
                                    <Link
                                        href={index({
                                            query: {
                                                ...filters,
                                                page: airports.current_page + 1,
                                            },
                                        })}
                                    >
                                        Next
                                        <ChevronRight />
                                    </Link>
                                </Button>
                            ) : null}
                        </div>
                    </nav>
                ) : null}
            </div>
            {creating ? (
                <AirportDialog
                    onClose={() => setCreating(false)}
                    onCreated={() => router.reload({ only: ['airports'] })}
                    returnFocus={() => trigger.current?.focus()}
                />
            ) : null}
        </>
    );
}
