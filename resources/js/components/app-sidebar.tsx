import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    CalendarDays,
    Plane,
    FolderGit2,
    Globe2,
    History,
    LayoutGrid,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as auditLogsIndex } from '@/routes/audit-logs';
import { index as firsIndex } from '@/routes/firs';
import { index as eventsIndex } from '@/routes/events';
import { index as airportsIndex } from '@/routes/airports';
import { index } from '@/routes/users';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage().props;
    const managementNavItems: NavItem[] = [
        ...(auth.can_view_events
            ? [
                  { title: 'Events', href: eventsIndex(), icon: CalendarDays },
                  { title: 'Airports', href: airportsIndex(), icon: Plane },
              ]
            : []),
        ...(auth.can_manage_users
            ? [{ title: 'Users', href: index(), icon: Users }]
            : []),
        ...(auth.can_manage_firs
            ? [{ title: 'FIRs', href: firsIndex(), icon: Globe2 }]
            : []),
        ...(auth.can_view_audit_logs
            ? [{ title: 'Audit log', href: auditLogsIndex(), icon: History }]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                {managementNavItems.length > 0 && (
                    <NavMain items={managementNavItems} label="Management" />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
