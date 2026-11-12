import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, type ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type AdminHeaderProps = {
    title: string;
    description: string;
    action?: ReactNode;
};

/**
 * The banner on the admin pages, with tabs for the sections the signed-in user may open.
 */
export function AdminHeader({ title, description, action }: AdminHeaderProps) {
    const { auth } = usePage().props;
    const { url } = usePage();
    const tabs = [
        auth.user?.can['manage-users'] && { label: 'Users', href: '/admin/users' },
        auth.user?.isAdministrator && { label: 'Roles', href: '/admin/roles' },
        auth.user?.isAdministrator && { label: 'FIRs', href: '/admin/firs' },
        auth.user?.isAdministrator && { label: 'Event types', href: '/admin/event-types' },
        auth.user?.isAdministrator && { label: 'Integrations', href: '/admin/integrations' },
        auth.user?.isAdministrator && { label: 'Settings', href: '/admin/settings' },
    ].filter((tab): tab is { label: string; href: string } => Boolean(tab));
    const tabsRef = useRef<HTMLElement>(null);

    // On phones the tabs scroll sideways; start with the active one in view. Each admin page mounts its own header.
    useEffect(() => {
        const active = tabsRef.current?.querySelector<HTMLElement>('[aria-current=page]');

        // After the web font loads, since it changes how wide the tabs are.
        void document.fonts.ready.then(() => active?.scrollIntoView({ block: 'nearest', inline: 'nearest' }));
    }, []);

    return (
        <section className="border-b bg-muted/40">
            <div className="mx-auto flex max-w-6xl flex-col gap-6 px-4 pt-12 sm:px-6 md:pt-16">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div className="flex min-w-0 flex-1 flex-col gap-4">
                        <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">{title}</h1>
                        <p className="min-h-[4lh] max-w-2xl text-lg text-muted-foreground sm:min-h-[3lh] lg:min-h-[2lh]">{description}</p>
                    </div>
                    <div className="flex min-h-9 shrink-0 items-start self-start">{action}</div>
                </div>
                {/* One line that scrolls sideways when the tabs don't fit, rather than wrapping or widening the page. */}
                <nav
                    ref={tabsRef}
                    className="-mb-px flex [scrollbar-width:none] gap-6 overflow-x-auto text-sm whitespace-nowrap [&::-webkit-scrollbar]:hidden"
                    aria-label="Admin sections"
                >
                    {tabs.map((tab) => {
                        const active = url === tab.href || url.startsWith(`${tab.href}/`) || url.startsWith(`${tab.href}?`);

                        return (
                            <Link
                                key={tab.href}
                                href={tab.href}
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'shrink-0 border-b-2 border-transparent pb-3 text-muted-foreground transition-colors hover:text-foreground',
                                    active && 'border-primary text-foreground',
                                )}
                            >
                                {tab.label}
                            </Link>
                        );
                    })}
                </nav>
            </div>
        </section>
    );
}

/**
 * The roles someone holds, as badges.
 */
export function RoleBadges({ roles }: { roles: string[] }) {
    if (roles.length === 0) {
        return <span className="text-xs text-muted-foreground">No roles</span>;
    }

    return (
        <div className="flex flex-wrap gap-1">
            {roles.map((role) => (
                <Badge key={role} variant="secondary">
                    {role}
                </Badge>
            ))}
        </div>
    );
}
