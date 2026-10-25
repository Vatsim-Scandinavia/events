import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { AdminHeader } from '@/components/admin-header';
import { useConfirm } from '@/components/confirm-dialog';
import { EventTypeBadge } from '@/components/event-card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout';

// Mirrors the types prop of App\Http\Controllers\Admin\EventTypeController@index.
type EventTypeSummary = {
    id: number;
    name: string;
    events: number;
};

const events = (count: number) => `${count} ${count === 1 ? 'event' : 'events'}`;

export default function EventTypesIndex({ types }: { types: EventTypeSummary[] }) {
    const confirm = useConfirm();

    const destroy = async (type: EventTypeSummary) => {
        const confirmed = await confirm({
            title: `Delete ${type.name}?`,
            description: type.events > 0 ? `Its ${events(type.events)} will show as General. This can’t be undone.` : 'This can’t be undone.',
            confirmLabel: 'Delete type',
            destructive: true,
        });

        if (confirmed) {
            router.delete(`/admin/event-types/${type.id}`, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Event types" />

            <AdminHeader
                title="Event types"
                description="The types staff can give an event. Events without one show as General."
                action={
                    <Button className="self-start" asChild>
                        <Link href="/admin/event-types/create">
                            <Plus />
                            New type
                        </Link>
                    </Button>
                }
            />

            <div className="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-8 sm:px-6">
                {types.length === 0 ? (
                    <p className="rounded-xl bg-card px-4 py-8 text-center text-muted-foreground ring-1 ring-foreground/10">
                        No types yet. Every event shows as General until you add one.
                    </p>
                ) : (
                    <ul className="divide-y overflow-hidden rounded-xl bg-card ring-1 ring-foreground/10">
                        {types.map((type) => (
                            <li key={type.id} className="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center">
                                <span className="flex flex-1 flex-wrap items-center gap-2 font-medium">
                                    <EventTypeBadge type={type.name} />
                                    <span className="text-sm font-normal text-muted-foreground">· {events(type.events)}</span>
                                </span>
                                <div className="flex gap-2">
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={`/admin/event-types/${type.id}/edit`}>
                                            <Pencil />
                                            Edit
                                        </Link>
                                    </Button>
                                    <Button variant="ghost" size="sm" onClick={() => destroy(type)} aria-label={`Delete ${type.name}`}>
                                        <Trash2 />
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

EventTypesIndex.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
