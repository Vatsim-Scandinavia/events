import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Search } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { AdminHeader, RoleBadges } from '@/components/admin-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/AppLayout';
import type { AdminUser, Paginated } from '@/types/admin';

type UsersIndexProps = {
    users: Paginated<AdminUser>;
    filters: { search: string; role: number | null; fir: number | null };
    roles: { id: number; name: string }[];
    firs: { id: number; icao: string; name: string }[];
};

export default function UsersIndex({ users, filters, roles, firs }: UsersIndexProps) {
    const { auth } = usePage().props;
    const [search, setSearch] = useState(filters.search);
    const first = useRef(true);

    const visit = (next: { search?: string; role?: number | null; fir?: number | null }) => {
        const query = { search: filters.search, role: filters.role, fir: filters.fir, ...next };

        router.get(
            '/admin/users',
            { search: query.search || undefined, role: query.role || undefined, fir: query.fir || undefined },
            { preserveState: true, replace: true },
        );
    };

    // Search as the person types, once they pause.
    useEffect(() => {
        if (first.current) {
            first.current = false;

            return;
        }

        const timer = setTimeout(() => visit({ search }), 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    return (
        <>
            <Head title="Users" />

            <AdminHeader title="Users" description="Everyone who has signed in to the hub. Open someone to choose their roles." />

            <div className="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-8 sm:px-6">
                <div className="flex flex-col gap-3 sm:flex-row">
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={auth.user?.isAdministrator ? 'Search by name, email or CID' : 'Search by name or CID'}
                            aria-label="Search users"
                            className="pl-8"
                        />
                    </div>
                    <div className="sm:w-56">
                        <NativeSelect
                            value={filters.role ?? ''}
                            onChange={(e) => visit({ role: e.target.value ? Number(e.target.value) : null })}
                            aria-label="Filter by role"
                        >
                            <option value="">All roles</option>
                            {roles.map((role) => (
                                <option key={role.id} value={role.id}>
                                    {role.name}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                    {firs.length > 0 && (
                        <div className="sm:w-48">
                            <NativeSelect
                                value={filters.fir ?? ''}
                                onChange={(e) => visit({ fir: e.target.value ? Number(e.target.value) : null })}
                                aria-label="Filter by FIR"
                            >
                                <option value="">Any FIR</option>
                                {firs.map((fir) => (
                                    <option key={fir.id} value={fir.id}>
                                        {fir.icao} {fir.name !== fir.icao ? fir.name : ''}
                                    </option>
                                ))}
                            </NativeSelect>
                        </div>
                    )}
                </div>

                {users.data.length > 0 ? (
                    <div className="overflow-hidden rounded-xl bg-card ring-1 ring-foreground/10">
                        <table className="w-full table-fixed text-sm">
                            <thead className="border-b bg-muted/40 text-left text-xs text-muted-foreground">
                                <tr>
                                    <th scope="col" className="px-4 py-2.5 font-medium">
                                        Name
                                    </th>
                                    <th scope="col" className="hidden w-28 px-4 py-2.5 font-medium sm:table-cell">
                                        CID
                                    </th>
                                    <th scope="col" className="hidden w-24 px-4 py-2.5 font-medium md:table-cell">
                                        Rating
                                    </th>
                                    <th scope="col" className="hidden w-72 px-4 py-2.5 font-medium lg:table-cell">
                                        Roles
                                    </th>
                                    <th scope="col" className="w-16 px-4 py-2.5 lg:w-32">
                                        <span className="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {users.data.map((user) => (
                                    <tr key={user.id} className="transition-colors hover:bg-muted/50">
                                        <td className="px-4 py-3 align-middle">
                                            <div className="flex min-w-0 flex-col gap-1">
                                                <span className="truncate font-medium text-foreground">{user.name}</span>
                                                {user.email && <span className="truncate text-xs text-muted-foreground">{user.email}</span>}
                                                <div className="lg:hidden">
                                                    <RoleBadges roles={user.roles} />
                                                </div>
                                            </div>
                                        </td>
                                        <td className="hidden px-4 py-3 align-middle text-muted-foreground tabular-nums sm:table-cell">
                                            {user.cid ?? '—'}
                                        </td>
                                        <td className="hidden px-4 py-3 align-middle text-muted-foreground md:table-cell">{user.rating ?? '—'}</td>
                                        <td className="hidden px-4 py-3 align-middle lg:table-cell">
                                            <RoleBadges roles={user.roles} />
                                        </td>
                                        <td className="px-4 py-3 text-right align-middle">
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link href={`/admin/users/${user.id}`} aria-label={`Manage ${user.name}`}>
                                                    <span className="hidden lg:inline">Manage</span>
                                                    <ChevronRight />
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <p className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">No users match that search.</p>
                )}

                {users.last_page > 1 && (
                    <div className="flex items-center justify-between gap-3 text-sm text-muted-foreground">
                        <span>
                            {users.from}–{users.to} of {users.total}
                        </span>
                        <div className="flex gap-2">
                            <PageLink href={users.prev_page_url} label="Previous">
                                <ChevronLeft />
                            </PageLink>
                            <PageLink href={users.next_page_url} label="Next">
                                <ChevronRight />
                            </PageLink>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

UsersIndex.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;

function PageLink({ href, label, children }: { href: string | null; label: string; children: ReactNode }) {
    if (!href) {
        return (
            <Button variant="outline" size="sm" disabled aria-label={label}>
                {children}
            </Button>
        );
    }

    return (
        <Button variant="outline" size="sm" asChild>
            <Link href={href} preserveState aria-label={label}>
                {children}
            </Link>
        </Button>
    );
}
