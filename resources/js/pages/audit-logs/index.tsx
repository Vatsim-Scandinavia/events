import { Head, Link, useForm } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, History, Search } from 'lucide-react';
import InputError from '@/components/input-error';
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
import { index } from '@/routes/audit-logs';
import type {
    AuditFilters,
    AuditLog,
    AuditValue,
    PaginatedAuditLogs,
    RoleSnapshot,
} from '@/types/audit-logs';

const events: Record<AuditLog['event'], string> = {
    created: 'Created',
    updated: 'Updated',
    deleted: 'Deleted',
    roles_updated: 'Roles changed',
    booked: 'Position booked',
    withdrawn: 'Booking withdrawn',
    interest_submitted: 'Interest submitted',
    interest_withdrawn: 'Interest withdrawn',
    published: 'Published',
    unpublished: 'Unpublished',
};

const fieldNames: Record<string, string> = {
    code: 'FIR code',
    name: 'Name',
    name_full: 'Name',
    email: 'Email',
    controller_rating: 'Controller rating',
    division: 'Division',
    subdivision: 'Subdivision',
    oauth_provider: 'Sign-in provider',
    roles: 'Role assignments',
    event_id: 'Event',
    occurrence_date: 'Occurrence date',
    mode: 'Roster type',
    is_open: 'Signups open',
    shifts: 'Shifts and bookings',
    positions: 'Selected positions',
    interests: 'Controller interest',
};

function ChangeValue({
    value,
    field,
}: {
    value: AuditValue | undefined;
    field: string;
}) {
    if (Array.isArray(value) && field === 'roles') {
        return value.length === 0 ? (
            <span className="text-muted-foreground">
                No assigned roles (Pilot)
            </span>
        ) : (
            <ul className="flex flex-col gap-2">
                {(value as RoleSnapshot[]).map((role) => (
                    <li
                        key={`${role.role}-${role.fir_id}-${role.source}`}
                        className="flex flex-col gap-0.5"
                    >
                        <span>
                            {role.role} · {role.fir ?? 'Global'}
                        </span>
                        <span className="text-muted-foreground text-xs">
                            {role.source}
                            {role.fir_id !== null
                                ? ` · FIR #${role.fir_id}`
                                : ''}
                        </span>
                    </li>
                ))}
            </ul>
        );
    }

    if (typeof value === 'object' && value !== null) {
        return (
            <pre className="text-xs wrap-anywhere whitespace-pre-wrap">
                {JSON.stringify(value, null, 2)}
            </pre>
        );
    }

    return value === null || value === undefined || value === '' ? (
        <span className="text-muted-foreground">Not set</span>
    ) : (
        <span className="wrap-anywhere whitespace-pre-wrap">{value}</span>
    );
}

function LogEntry({ log }: { log: AuditLog }) {
    const fields = [
        ...new Set([
            ...Object.keys(log.old_values),
            ...Object.keys(log.new_values),
        ]),
    ];

    return (
        <details className="group">
            <summary className="hover:bg-muted/30 focus-visible:ring-ring flex cursor-pointer list-none flex-col gap-3 p-4 focus-visible:ring-2 focus-visible:outline-none md:flex-row md:items-center">
                <ChevronRight
                    aria-hidden="true"
                    className="text-muted-foreground hidden size-4 shrink-0 transition-transform group-open:rotate-90 md:block"
                />
                <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge
                            variant={
                                log.event === 'deleted'
                                    ? 'outline'
                                    : 'secondary'
                            }
                        >
                            {events[log.event]}
                        </Badge>
                        <span className="text-muted-foreground text-xs">
                            {
                                {
                                    fir: 'FIR',
                                    user: 'User',
                                    event: 'Event',
                                    airport: 'Airport',
                                    roster: 'Roster',
                                }[log.subject_type]
                            }{' '}
                            #{log.subject_id}
                        </span>
                    </div>
                    <span className="font-medium wrap-anywhere">
                        {log.subject_label}
                    </span>
                </div>
                <div className="flex min-w-0 flex-1 flex-col gap-1 text-sm">
                    <span className="wrap-anywhere">
                        {log.actor_name ?? 'System / CLI'}
                        {log.actor_cid !== null ? ` (${log.actor_cid})` : ''}
                    </span>
                    <span className="text-muted-foreground text-xs wrap-anywhere">
                        Source: {log.source}
                    </span>
                </div>
                <div className="flex shrink-0 items-center justify-between gap-3 text-xs md:flex-col md:items-end">
                    <time
                        dateTime={log.created_at}
                        className="text-muted-foreground"
                    >
                        {new Date(log.created_at)
                            .toISOString()
                            .slice(0, 19)
                            .replace('T', ' ')}{' '}
                        UTC
                    </time>
                    <span className="underline underline-offset-4 group-open:hidden">
                        View changes
                    </span>
                    <span className="hidden underline underline-offset-4 group-open:inline">
                        Hide changes
                    </span>
                </div>
            </summary>
            <div className="bg-muted/20 flex flex-col gap-4 border-t p-4 md:px-10">
                {fields.map((field) => (
                    <section
                        key={field}
                        aria-label={fieldNames[field] ?? field}
                        className="flex flex-col gap-2"
                    >
                        <h3 className="text-sm font-semibold">
                            {fieldNames[field] ?? field}
                        </h3>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="bg-background flex min-w-0 flex-col gap-2 rounded-lg border p-3 text-sm">
                                <span className="text-muted-foreground text-xs font-medium">
                                    Before
                                </span>
                                <ChangeValue
                                    value={log.old_values[field]}
                                    field={field}
                                />
                            </div>
                            <div className="bg-background flex min-w-0 flex-col gap-2 rounded-lg border p-3 text-sm">
                                <span className="text-muted-foreground text-xs font-medium">
                                    After
                                </span>
                                <ChangeValue
                                    value={log.new_values[field]}
                                    field={field}
                                />
                            </div>
                        </div>
                    </section>
                ))}
            </div>
        </details>
    );
}

export default function AuditLogs({
    logs,
    filters,
}: {
    logs: PaginatedAuditLogs;
    filters: AuditFilters;
}) {
    const form = useForm(filters);
    const hasFilters = Object.values(filters).some(Boolean);
    const pageUrl = (page: number) => index({ query: { ...filters, page } });

    return (
        <>
            <Head title="Audit log" />
            <div className="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-8">
                <header className="flex flex-col gap-2">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Audit log
                        </h1>
                        <Badge variant="secondary">
                            {logs.total} {hasFilters ? 'matching' : 'total'}
                        </Badge>
                    </div>
                    <p className="text-muted-foreground text-sm">
                        See who changed FIRs, user profiles, and role
                        assignments. Expand an entry to compare values.
                    </p>
                    <p className="text-muted-foreground text-xs">
                        History starts when auditing is enabled. All times and
                        date filters use UTC.
                    </p>
                </header>

                <div className="flex min-w-0 flex-col overflow-hidden rounded-xl border">
                    <form
                        className="flex flex-col gap-4 p-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.get(index.url(), {
                                preserveState: 'errors',
                                preserveScroll: true,
                            });
                        }}
                    >
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="audit-search">Search history</Label>
                            <Input
                                id="audit-search"
                                name="search"
                                type="search"
                                maxLength={255}
                                placeholder="Name, FIR, source, or exact CID / record ID"
                                value={form.data.search}
                                onChange={(event) =>
                                    form.setData('search', event.target.value)
                                }
                                aria-invalid={!!form.errors.search}
                                aria-describedby="audit-search-error"
                            />
                            <InputError
                                id="audit-search-error"
                                message={form.errors.search}
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="audit-type">Record type</Label>
                                <Select
                                    value={form.data.subject_type || 'all'}
                                    onValueChange={(value) =>
                                        form.setData(
                                            'subject_type',
                                            value === 'all' ? '' : value,
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id="audit-type"
                                        className="w-full"
                                        aria-invalid={
                                            !!form.errors.subject_type
                                        }
                                        aria-describedby="audit-type-error"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="all">
                                                All records
                                            </SelectItem>
                                            <SelectItem value="fir">
                                                FIRs
                                            </SelectItem>
                                            <SelectItem value="user">
                                                Users
                                            </SelectItem>
                                            <SelectItem value="event">
                                                Events
                                            </SelectItem>
                                            <SelectItem value="airport">
                                                Airports
                                            </SelectItem>
                                            <SelectItem value="roster">
                                                Rosters
                                            </SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    id="audit-type-error"
                                    message={form.errors.subject_type}
                                />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="audit-event">Action</Label>
                                <Select
                                    value={form.data.event || 'all'}
                                    onValueChange={(value) =>
                                        form.setData(
                                            'event',
                                            value === 'all' ? '' : value,
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id="audit-event"
                                        className="w-full"
                                        aria-invalid={!!form.errors.event}
                                        aria-describedby="audit-event-error"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="all">
                                                All actions
                                            </SelectItem>
                                            {Object.entries(events).map(
                                                ([value, label]) => (
                                                    <SelectItem
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {label}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    id="audit-event-error"
                                    message={form.errors.event}
                                />
                            </div>
                            {(['from', 'to'] as const).map((field) => (
                                <div
                                    key={field}
                                    className="flex flex-col gap-2"
                                >
                                    <Label htmlFor={`audit-${field}`}>
                                        {field === 'from'
                                            ? 'From date'
                                            : 'To date'}{' '}
                                        (UTC)
                                    </Label>
                                    <Input
                                        id={`audit-${field}`}
                                        name={field}
                                        type="date"
                                        value={form.data[field]}
                                        onChange={(event) =>
                                            form.setData(
                                                field,
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={!!form.errors[field]}
                                        aria-describedby={`audit-${field}-error`}
                                    />
                                    <InputError
                                        id={`audit-${field}-error`}
                                        message={form.errors[field]}
                                    />
                                </div>
                            ))}
                        </div>
                        <div className="flex items-center gap-3">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? (
                                    <Spinner />
                                ) : (
                                    <Search data-icon="inline-start" />
                                )}
                                Apply filters
                            </Button>
                            {hasFilters ? (
                                <Button variant="ghost" asChild>
                                    <Link href={index()}>Clear filters</Link>
                                </Button>
                            ) : null}
                        </div>
                    </form>

                    <div className="divide-y border-t">
                        {logs.data.map((log) => (
                            <LogEntry key={log.id} log={log} />
                        ))}
                    </div>
                    {logs.data.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 px-6 py-14 text-center">
                            <History
                                aria-hidden="true"
                                className="text-muted-foreground size-8"
                            />
                            <h2 className="font-semibold">
                                {hasFilters
                                    ? 'No changes match these filters'
                                    : 'No changes recorded yet'}
                            </h2>
                            <p className="text-muted-foreground max-w-md text-sm">
                                {hasFilters
                                    ? 'Try another name, action, or date range.'
                                    : 'Future FIR edits, profile updates, and role changes will appear here.'}
                            </p>
                            {hasFilters ? (
                                <Button variant="outline" asChild>
                                    <Link href={index()}>Clear filters</Link>
                                </Button>
                            ) : null}
                        </div>
                    ) : null}
                    <footer className="text-muted-foreground flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3 text-sm">
                        <span>
                            {logs.total === 0
                                ? '0 changes'
                                : `Showing ${logs.from ?? 0}–${logs.to ?? 0} of ${logs.total} changes`}
                        </span>
                        <nav
                            aria-label="Audit log pagination"
                            className="flex items-center gap-2"
                        >
                            {logs.current_page > 1 ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={pageUrl(logs.current_page - 1)}>
                                        <ChevronLeft data-icon="inline-start" />
                                        Previous
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" size="sm" disabled>
                                    <ChevronLeft data-icon="inline-start" />
                                    Previous
                                </Button>
                            )}
                            {logs.current_page < logs.last_page ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={pageUrl(logs.current_page + 1)}>
                                        Next
                                        <ChevronRight data-icon="inline-end" />
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" size="sm" disabled>
                                    Next
                                    <ChevronRight data-icon="inline-end" />
                                </Button>
                            )}
                        </nav>
                    </footer>
                </div>
            </div>
        </>
    );
}
