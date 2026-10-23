import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { signInUrl } from '@/lib/utils';

// Brings people straight back to this event once they've signed in.
export default function SignInPrompt({ children }: { children: ReactNode }) {
    const { url } = usePage();

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl px-4 py-3 text-sm ring-1 ring-foreground/10">
            <span className="text-muted-foreground">{children}</span>
            <Button size="sm" asChild>
                <a href={signInUrl(url)}>Sign in with VATSIM</a>
            </Button>
        </div>
    );
}
