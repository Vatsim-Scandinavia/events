import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { AdminHeader } from '@/components/admin-header';
import { useConfirm } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout';

// Mirrors the firs prop of App\Http\Controllers\Admin\FirController@index.
type FirSummary = {
    id: number;
    icao: string;
    name: string;
    events: number;
};

const events = (count: number) => `${count} ${count === 1 ? 'event' : 'events'}`;

export default function FirsIndex({ firs }: { firs: FirSummary[] }) {
    const confirm = useConfirm();

    const destroy = async (fir: FirSummary) => {
        const confirmed = await confirm({
            title: `Delete ${fir.icao} ${fir.name}?`,
            description: 'This can’t be undone.',
            confirmLabel: 'Delete FIR',
            destructive: true,
        });

        if (confirmed) {
            router.delete(`/admin/firs/${fir.id}`, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="FIRs" />

            <AdminHeader
                title="FIRs"
                description="The FIRs your vACC covers. Every event belongs to one of them."
                action={
                    <Button className="self-start" asChild>
                        <Link href="/admin/firs/create">
                            <Plus />
                            New FIR
                        </Link>
                    </Button>
                }
            />

            <div className="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-8 sm:px-6">
                {firs.length === 0 ? (
                    <p className="rounded-xl bg-card px-4 py-8 text-center text-muted-foreground ring-1 ring-foreground/10">
                        No FIRs yet. Add one before creating events.
                    </p>
                ) : (
                    <ul className="divide-y overflow-hidden rounded-xl bg-card ring-1 ring-foreground/10">
                        {firs.map((fir) => (
                            <li key={fir.id} className="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center">
                                <span className="flex flex-1 flex-wrap items-center gap-2 font-medium">
                                    <Badge variant="outline" className="font-mono">
                                        {fir.icao}
                                    </Badge>
                                    {fir.name}
                                    <span className="text-sm font-normal text-muted-foreground">· {events(fir.events)}</span>
                                </span>
                                <div className="flex gap-2">
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={`/admin/firs/${fir.id}/edit`}>
                                            <Pencil />
                                            Edit
                                        </Link>
                                    </Button>
                                    {fir.events === 0 && (
                                        <Button variant="ghost" size="sm" onClick={() => destroy(fir)} aria-label={`Delete ${fir.icao}`}>
                                            <Trash2 />
                                        </Button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

FirsIndex.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
