import { Head, Link } from '@inertiajs/react';
import { Compass, Hourglass, Lock, RefreshCw, TriangleAlert } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout';

type ErrorDetails = { title: string; message: string; icon: LucideIcon };

const errors: Record<number, ErrorDetails> = {
    403: { title: 'Not allowed', message: 'You don’t have permission to see this page. If you think you should, ask an admin.', icon: Lock },
    404: { title: 'Page not found', message: 'This page doesn’t exist, or the event has been removed.', icon: Compass },
    419: { title: 'Page expired', message: 'You were away for a while, so the page expired. Reload it and try again.', icon: RefreshCw },
    429: { title: 'Slow down', message: 'That was a lot of requests in a short time. Wait a minute and try again.', icon: Hourglass },
    503: { title: 'Back soon', message: 'The hub is down for maintenance. Try again in a few minutes.', icon: Hourglass },
};

const fallback: ErrorDetails = {
    title: 'Something went wrong',
    message: 'The hub ran into a problem. Try again, and if it keeps happening let the vACC staff know.',
    icon: TriangleAlert,
};

export default function Error({ status }: { status: number }) {
    const { title, message, icon: Icon } = errors[status] ?? fallback;

    return (
        <>
            <Head title={title} />

            <div className="mx-auto flex max-w-xl flex-col items-center px-4 py-24 text-center sm:px-6">
                <span className="mb-6 flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                    <Icon className="size-6" />
                </span>
                <p className="font-mono text-sm text-muted-foreground">Error {status}</p>
                <h1 className="mt-1 text-2xl font-semibold tracking-tight">{title}</h1>
                <p className="mt-3 text-muted-foreground">{message}</p>

                <div className="mt-8 flex flex-wrap justify-center gap-3">
                    <Button asChild>
                        <Link href="/events">Browse events</Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href="/">Home</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

Error.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
