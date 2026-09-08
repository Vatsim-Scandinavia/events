import { Head, Link, useForm } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Search,
    ShieldCheck,
    Users as UsersIcon,
} from 'lucide-react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { UserRoleDialog } from '@/components/user-role-dialog';
import { useInitials } from '@/hooks/use-initials';
import { index } from '@/routes/users';
import type {
    PaginatedUsers,
    UserFilters,
    UserRole,
    UserTeam,
} from '@/types/users';

type Props = {
    users: PaginatedUsers;
    filters: UserFilters;
    teams: UserTeam[];
    roles: UserRole[];
};

export default function Users({ users, filters, teams, roles }: Props) {
    const initials = useInitials();
    const { data, setData, get, processing, errors } =
        useForm<UserFilters>(filters);
    const [selectedCid, setSelectedCid] = useState<number | null>(null);
    const trigger = useRef<HTMLButtonElement | null>(null);
    const selectedUser = users.data.find((user) => user.cid === selectedCid);
    const filtered = !!(filters.search || filters.role || filters.team_id);
    const pageUrl = (page: number) => index({ query: { ...filters, page } });

    return (
        <>
            <Head title="Users" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex flex-col gap-2">
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Users
                            </h1>
                            <Badge variant="secondary">
                                {users.total} {filtered ? 'matching' : 'total'}
                            </Badge>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            Find members and manage their roles across FIRs.
                        </p>
                    </div>
                    <Badge variant="outline">
                        <ShieldCheck data-icon="inline-start" />
                        Administrator access
                    </Badge>
                </header>

                <div className="flex min-w-0 flex-col overflow-hidden rounded-xl border">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            get(index.url(), {
                                preserveState: false,
                                preserveScroll: true,
                            });
                        }}
                        className="flex flex-col gap-3 p-4"
                    >
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-end">
                            <div className="flex flex-1 flex-col gap-2">
                                <Label htmlFor="user-search">
                                    Search users
                                </Label>
                                <Input
                                    id="user-search"
                                    name="search"
                                    type="search"
                                    maxLength={255}
                                    value={data.search}
                                    onChange={(event) =>
                                        setData('search', event.target.value)
                                    }
                                    placeholder="Name, email or exact CID"
                                    aria-invalid={!!errors.search}
                                    aria-describedby="search-error"
                                />
                            </div>
                            <div className="flex flex-col gap-3 sm:flex-row">
                                <div className="flex flex-1 flex-col gap-2 lg:w-48">
                                    <Label htmlFor="role-filter">Role</Label>
                                    <Select
                                        name="role"
                                        value={data.role ?? 'all'}
                                        onValueChange={(role) =>
                                            setData(
                                                'role',
                                                role === 'all' ? null : role,
                                            )
                                        }
                                    >
                                        <SelectTrigger
                                            id="role-filter"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem value="all">
                                                    All roles
                                                </SelectItem>
                                                {roles.map((role) => (
                                                    <SelectItem
                                                        key={role.name}
                                                        value={role.name}
                                                    >
                                                        {role.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="flex flex-1 flex-col gap-2 lg:w-48">
                                    <Label htmlFor="fir-filter">FIR</Label>
                                    <Select
                                        name="team_id"
                                        value={
                                            data.team_id
                                                ? String(data.team_id)
                                                : 'all'
                                        }
                                        onValueChange={(teamId) =>
                                            setData(
                                                'team_id',
                                                teamId === 'all'
                                                    ? null
                                                    : Number(teamId),
                                            )
                                        }
                                    >
                                        <SelectTrigger
                                            id="fir-filter"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem value="all">
                                                    All FIRs
                                                </SelectItem>
                                                {teams.map((team) => (
                                                    <SelectItem
                                                        key={team.id}
                                                        value={String(team.id)}
                                                    >
                                                        {team.code} ·{' '}
                                                        {team.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <Button type="submit" disabled={processing}>
                                {processing ? (
                                    <Spinner />
                                ) : (
                                    <Search data-icon="inline-start" />
                                )}
                                Search
                            </Button>
                            {filtered && (
                                <Button variant="ghost" asChild>
                                    <Link href={index()}>Clear filters</Link>
                                </Button>
                            )}
                        </div>
                        <InputError
                            id="search-error"
                            message={
                                errors.search || errors.role || errors.team_id
                            }
                        />
                    </form>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <caption className="sr-only">
                                Users and their current roles. Use Manage roles
                                to edit assignments.
                            </caption>
                            <thead className="bg-muted/50 text-muted-foreground border-y">
                                <tr>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-medium"
                                    >
                                        Member
                                    </th>
                                    <th
                                        scope="col"
                                        className="hidden px-4 py-3 font-medium md:table-cell"
                                    >
                                        CID
                                    </th>
                                    <th
                                        scope="col"
                                        className="hidden px-4 py-3 font-medium sm:table-cell"
                                    >
                                        Roles
                                    </th>
                                    <th
                                        scope="col"
                                        className="hidden px-4 py-3 font-medium xl:table-cell"
                                    >
                                        FIRs
                                    </th>
                                    <th
                                        scope="col"
                                        className="hidden px-4 py-3 font-medium xl:table-cell"
                                    >
                                        Joined
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        <span className="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {users.data.map((user) => {
                                    const firs = teams.filter((team) =>
                                        user.grants.some(
                                            (grant) =>
                                                grant.team_id === team.id,
                                        ),
                                    );
                                    return (
                                        <tr
                                            key={user.cid}
                                            className="hover:bg-muted/30 transition-colors"
                                        >
                                            <td className="px-4 py-4">
                                                <div className="flex items-center gap-3">
                                                    <Avatar className="hidden size-9 sm:flex">
                                                        <AvatarFallback>
                                                            {initials(
                                                                user.name_full,
                                                            )}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div className="flex min-w-0 flex-col gap-1">
                                                        <span className="font-medium">
                                                            {user.name_full}
                                                        </span>
                                                        <span
                                                            className="text-muted-foreground max-w-40 truncate text-xs sm:max-w-64"
                                                            title={user.email}
                                                        >
                                                            {user.email}
                                                        </span>
                                                        <span className="text-muted-foreground text-xs md:hidden">
                                                            CID {user.cid}
                                                        </span>
                                                        <div className="flex flex-wrap gap-1.5 sm:hidden">
                                                            {user.roles.map(
                                                                (role) => (
                                                                    <Badge
                                                                        key={
                                                                            role
                                                                        }
                                                                        variant={
                                                                            role ===
                                                                            'Administrator'
                                                                                ? 'default'
                                                                                : 'secondary'
                                                                        }
                                                                    >
                                                                        {role}
                                                                    </Badge>
                                                                ),
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="text-muted-foreground hidden px-4 py-4 tabular-nums md:table-cell">
                                                {user.cid}
                                            </td>
                                            <td className="hidden px-4 py-4 sm:table-cell">
                                                <div className="flex flex-wrap gap-1.5">
                                                    {user.roles.map((role) => (
                                                        <Badge
                                                            key={role}
                                                            variant={
                                                                role ===
                                                                'Administrator'
                                                                    ? 'default'
                                                                    : 'secondary'
                                                            }
                                                        >
                                                            {role}
                                                        </Badge>
                                                    ))}
                                                </div>
                                            </td>
                                            <td className="hidden px-4 py-4 xl:table-cell">
                                                <div className="flex flex-wrap gap-1.5">
                                                    {firs.length > 0 ? (
                                                        firs.map((team) => (
                                                            <Badge
                                                                key={team.id}
                                                                variant="outline"
                                                                title={
                                                                    team.name
                                                                }
                                                            >
                                                                {team.code}
                                                            </Badge>
                                                        ))
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            Global
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="text-muted-foreground hidden px-4 py-4 whitespace-nowrap tabular-nums xl:table-cell">
                                                {user.joined_at ?? '—'}
                                            </td>
                                            <td className="px-4 py-4 text-right">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    aria-label={`Manage roles for ${user.name_full}`}
                                                    onClick={(event) => {
                                                        trigger.current =
                                                            event.currentTarget;
                                                        setSelectedCid(
                                                            user.cid,
                                                        );
                                                    }}
                                                >
                                                    <ShieldCheck
                                                        className="sm:hidden"
                                                        data-icon="inline-start"
                                                    />
                                                    <span className="hidden sm:inline">
                                                        Manage roles
                                                    </span>
                                                </Button>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {users.data.length === 0 && (
                        <div
                            className="flex flex-col items-center gap-3 px-6 py-16 text-center"
                            role="status"
                        >
                            <UsersIcon className="text-muted-foreground size-8" />
                            <h2 className="font-semibold">
                                {filtered
                                    ? 'No users match your filters'
                                    : 'No users to show'}
                            </h2>
                            <p className="text-muted-foreground max-w-sm text-sm">
                                {filtered
                                    ? 'Try a different name, email or CID, or clear the role and FIR filters.'
                                    : 'Members appear here after signing in with their VATSIM account.'}
                            </p>
                            {filtered && (
                                <Button variant="outline" asChild>
                                    <Link href={index()}>Clear filters</Link>
                                </Button>
                            )}
                        </div>
                    )}

                    <footer className="flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3">
                        <p
                            className="text-muted-foreground text-xs"
                            aria-live="polite"
                        >
                            {users.total > 0
                                ? `Showing ${users.from ?? 0}–${users.to ?? 0} of ${users.total} users`
                                : '0 users'}
                        </p>
                        <nav
                            aria-label="User pagination"
                            className="flex items-center gap-2"
                        >
                            {users.current_page > 1 ? (
                                <Button variant="outline" size="icon" asChild>
                                    <Link
                                        href={pageUrl(users.current_page - 1)}
                                        aria-label="Previous page"
                                    >
                                        <ChevronLeft />
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    variant="outline"
                                    size="icon"
                                    disabled
                                    aria-label="Previous page"
                                >
                                    <ChevronLeft />
                                </Button>
                            )}
                            <span className="text-muted-foreground px-2 text-xs tabular-nums">
                                Page {users.current_page} of {users.last_page}
                            </span>
                            {users.current_page < users.last_page ? (
                                <Button variant="outline" size="icon" asChild>
                                    <Link
                                        href={pageUrl(users.current_page + 1)}
                                        aria-label="Next page"
                                    >
                                        <ChevronRight />
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    variant="outline"
                                    size="icon"
                                    disabled
                                    aria-label="Next page"
                                >
                                    <ChevronRight />
                                </Button>
                            )}
                        </nav>
                    </footer>
                </div>

                <Alert>
                    <ShieldCheck />
                    <AlertDescription>
                        Roles control access to the application. Administrator
                        is global; Event Coordinator, vACC Staff and Controller
                        are assigned per FIR. Pilot is the default when no roles
                        are assigned.
                    </AlertDescription>
                </Alert>
            </div>
            {selectedUser && (
                <UserRoleDialog
                    key={selectedUser.cid}
                    user={selectedUser}
                    teams={teams}
                    roles={roles}
                    onClose={() => setSelectedCid(null)}
                    returnFocus={() => trigger.current?.focus()}
                />
            )}
        </>
    );
}

Users.layout = { breadcrumbs: [{ title: 'Users', href: index() }] };
