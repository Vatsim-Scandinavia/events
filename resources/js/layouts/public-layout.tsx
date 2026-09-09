import { Link, usePage } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';
import { index } from '@/routes/events';

export default function PublicLayout({ children }: { children: ReactNode }) {
    const { auth, name } = usePage().props;

    return (
        <div className="bg-background text-foreground min-h-svh">
            <header className="border-b">
                <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-4 md:px-8">
                    <Link
                        href={index()}
                        className="flex items-center gap-3 font-semibold"
                    >
                        <CalendarDays className="size-5" />
                        {name}
                    </Link>
                    <nav
                        aria-label="Public navigation"
                        className="flex flex-wrap items-center gap-4"
                    >
                        <Link
                            href={index()}
                            className="text-sm hover:underline"
                        >
                            Events
                        </Link>
                        <Button asChild variant="outline" size="sm">
                            <Link href={auth.user ? dashboard() : login()}>
                                {auth.user ? 'Dashboard' : 'Sign in'}
                            </Link>
                        </Button>
                    </nav>
                </div>
            </header>
            <main className="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-8">
                {children}
            </main>
        </div>
    );
}
