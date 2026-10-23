import { Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, CircleAlert, CircleCheck, CircleUser, LogOut, Menu, Plane, Settings, X } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { ConfirmProvider } from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn, signInUrl } from '@/lib/utils';
import type { AuthUser } from '@/types/inertia';

type AppLayoutProps = {
    children: ReactNode;
};

type NavigationItem = { label: string; href: string; section?: string };

const navigation: NavigationItem[] = [{ label: 'Events', href: '/events' }];

// Errors that aren't tied to a form field, raised by sign-in and the staffing and role rules on the server.
const generalErrorKeys = ['signIn', 'booking', 'interest', 'roster', 'staffing', 'role', 'fir', 'cancel'];

export default function AppLayout({ children }: AppLayoutProps) {
    const { vacc, auth } = usePage().props;
    const { url } = usePage();
    const [menuOpen, setMenuOpen] = useState(false);
    const admin = auth.user?.can['manage-users']
        ? '/admin/users'
        : auth.user?.can['manage-roles']
          ? '/admin/roles'
          : auth.user?.can['manage-settings']
            ? '/admin/settings'
            : null;
    const links = admin ? [...navigation, { label: 'Admin', href: admin, section: '/admin' }] : navigation;

    const isActive = (item: NavigationItem) => {
        const section = item.section ?? item.href;

        return url === section || url.startsWith(`${section}/`) || url.startsWith(`${section}?`);
    };

    return (
        <ConfirmProvider>
            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <header className="sticky top-0 z-40 border-b bg-background/80 backdrop-blur">
                    <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
                        <Link href="/" className="flex items-center gap-2 font-semibold">
                            {vacc.logo ? (
                                // Decorative: the vACC's name is right next to it.
                                <img src={vacc.logo} alt="" className="size-8 object-contain" />
                            ) : (
                                <span className="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                    <Plane className="size-4" />
                                </span>
                            )}
                            <span className="flex flex-col leading-tight">
                                {vacc.name}
                                <span className="text-xs font-normal text-muted-foreground">Event Hub</span>
                            </span>
                        </Link>

                        <nav aria-label="Main" className="hidden items-center gap-6 text-sm text-muted-foreground md:flex">
                            {links.map((item) => (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    aria-current={isActive(item) ? 'page' : undefined}
                                    className={cn('transition-colors hover:text-foreground', isActive(item) && 'text-foreground')}
                                >
                                    {item.label}
                                </Link>
                            ))}
                        </nav>

                        <div className="flex items-center gap-2 sm:gap-3">
                            {auth.user ? (
                                <UserMenu user={auth.user} />
                            ) : (
                                <>
                                    {auth.devLogin && (
                                        <Button variant="ghost" size="sm" asChild>
                                            <Link href="/login">Dev sign-in</Link>
                                        </Button>
                                    )}
                                    <Button size="sm" asChild>
                                        {/* A full page visit: VATSIM Connect is on another origin. */}
                                        <a href={signInUrl(url)}>Sign in with VATSIM</a>
                                    </Button>
                                </>
                            )}

                            <Popover open={menuOpen} onOpenChange={setMenuOpen}>
                                <PopoverTrigger asChild>
                                    <Button variant="ghost" size="icon" className="md:hidden" aria-label="Open menu">
                                        <Menu />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent align="end" className="w-60 p-1.5">
                                    <nav aria-label="Main" className="flex flex-col">
                                        {links.map((item) => (
                                            <Link
                                                key={item.href}
                                                href={item.href}
                                                onClick={() => setMenuOpen(false)}
                                                aria-current={isActive(item) ? 'page' : undefined}
                                                className={cn(
                                                    'rounded-lg px-3 py-2.5 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
                                                    isActive(item) && 'bg-accent text-foreground',
                                                )}
                                            >
                                                {item.label}
                                            </Link>
                                        ))}
                                    </nav>
                                </PopoverContent>
                            </Popover>
                        </div>
                    </div>
                </header>

                <Notices />

                <main className="flex-1">{children}</main>

                <footer className="border-t">
                    <div className="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-8 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <p>{vacc.name} Event Hub · Part of the VATSIM network</p>
                        <p>For flight simulation use only. Not for real-world navigation.</p>
                    </div>
                </footer>
            </div>
        </ConfirmProvider>
    );
}

// Who is signed in, with their settings and sign-out tucked behind their name.
function UserMenu({ user }: { user: AuthUser }) {
    // Members without a role are the hub's audience: pilots.
    // "Pilot" when they hold no role; roles held in one FIR read like "Event Staff · EKDK".
    const role = user.roles.join(', ');
    const details = [user.cid, user.rating].filter(Boolean).join(' · ');

    return (
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" className="h-auto gap-2 px-2 py-1.5" aria-label={`Account menu for ${user.name}`}>
                    <CircleUser className="size-5 sm:hidden" />
                    <span className="hidden flex-col text-right text-sm leading-tight font-normal sm:flex">
                        {user.name}
                        <span className="text-xs text-muted-foreground">{role}</span>
                    </span>
                    <ChevronDown className="hidden text-muted-foreground sm:block" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-60">
                <DropdownMenuLabel className="flex flex-col gap-0.5 font-normal">
                    <span className="text-sm font-medium text-foreground">{user.name}</span>
                    <span>{role}</span>
                    {details && <span className="tabular-nums">{details}</span>}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href="/settings">
                        <Settings />
                        Settings
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem onSelect={() => router.post('/logout')}>
                    <LogOut />
                    Sign out
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

// The outcome of the last action: a success message or a rule the server refused it on.
function Notices() {
    const { flash, props } = usePage();
    const error = generalErrorKeys.map((key) => props.errors[key]).find(Boolean);
    const message = error ?? flash.success;
    const [dismissed, setDismissed] = useState<string | null>(null);

    useEffect(() => setDismissed(null), [message]);

    if (!message || dismissed === message) {
        return null;
    }

    return (
        <div className="mx-auto w-full max-w-6xl px-4 pt-4 sm:px-6">
            <div
                role={error ? 'alert' : 'status'}
                className={cn(
                    'flex items-start gap-3 rounded-lg px-3 py-2.5 text-sm ring-1',
                    error ? 'bg-destructive/10 text-destructive ring-destructive/30' : 'bg-success/10 text-success ring-success/30',
                )}
            >
                {error ? <CircleAlert className="mt-0.5 size-4 shrink-0" /> : <CircleCheck className="mt-0.5 size-4 shrink-0" />}
                <p className="flex-1">{message}</p>
                <button type="button" onClick={() => setDismissed(message)} aria-label="Dismiss" className="opacity-70 hover:opacity-100">
                    <X className="size-4" />
                </button>
            </div>
        </div>
    );
}
