import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
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
        auth.user?.can['manage-roles'] && { label: 'Roles', href: '/admin/roles' },
        auth.user?.can['manage-settings'] && { label: 'FIRs', href: '/admin/firs' },
        auth.user?.can['manage-settings'] && { label: 'Event types', href: '/admin/event-types' },
        auth.user?.can['manage-settings'] && { label: 'Settings', href: '/admin/settings' },
    ].filter((tab): tab is { label: string; href: string } => Boolean(tab));

    return (
        <section className="border-b bg-muted/40">
            <div className="mx-auto flex max-w-6xl flex-col gap-3 px-4 pt-12 sm:px-6 md:pt-16">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div className="flex flex-col gap-3">
                        <h1 className="text-3xl font-semibold tracking-tight sm:text-4xl">{title}</h1>
                        <p className="max-w-2xl text-lg text-muted-foreground">{description}</p>
                    </div>
                    {action}
                </div>
                <nav className="mt-4 -mb-px flex gap-6 text-sm" aria-label="Admin sections">
                    {tabs.map((tab) => {
                        const active = url === tab.href || url.startsWith(`${tab.href}/`) || url.startsWith(`${tab.href}?`);

                        return (
                            <Link
                                key={tab.href}
                                href={tab.href}
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'border-b-2 border-transparent pb-3 text-muted-foreground transition-colors hover:text-foreground',
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
