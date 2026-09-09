import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Plus,
    Search,
} from 'lucide-react';
import { toast } from 'sonner';
import { EventBanner } from '@/components/event-banner';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { eventTime } from '@/lib/event-time';
import { create, index, show } from '@/routes/events';
import { update as accept } from '@/routes/events/collaborations';
import type { EventListing, Pagination } from '@/types/events';

type Props = {
    events: Pagination<EventListing>;
    filters: { search: string; status: string };
    can_create?: boolean;
    invitations?: {
        id: number;
        event_id: number;
        owner_code: string;
        team_code: string;
    }[];
};
export default function Events({
    events,
    filters,
    can_create = false,
    invitations,
}: Props) {
    const form = useForm(filters);
    const pendingInvitations = invitations ?? [];
    return (
        <>
            <Head title="Events" />
            <div className="flex flex-1 flex-col gap-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex flex-col gap-2">
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                Events
                            </h1>
                            <Badge variant="secondary">{events.total}</Badge>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            Explore events from participating FIRs. All times
                            below are UTC / Zulu.
                        </p>
                    </div>
                    {can_create ? (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus data-icon="inline-start" />
                                Create event
                            </Link>
                        </Button>
                    ) : null}
                </header>
                {pendingInvitations.length ? (
                    <Alert>
                        <AlertTitle>Collaboration invitations</AlertTitle>
                        <AlertDescription>
                            <ul className="flex flex-col gap-3">
                                {pendingInvitations.map((invitation) => (
                                    <li
                                        key={invitation.id}
                                        className="flex flex-wrap items-center gap-3"
                                    >
                                        <span>
                                            {invitation.owner_code} invited{' '}
                                            {invitation.team_code} to
                                            collaborate on an event.
                                        </span>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                router.patch(
                                                    accept.url({
                                                        event: invitation.event_id,
                                                        collaboration:
                                                            invitation.id,
                                                    }),
                                                    {},
                                                    {
                                                        onSuccess: () =>
                                                            toast.success(
                                                                'Invitation accepted.',
                                                            ),
                                                    },
                                                )
                                            }
                                        >
                                            Accept for {invitation.team_code}
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                ) : null}
                <form
                    className="flex flex-wrap items-end gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.get(index.url(), { preserveState: 'errors' });
                    }}
                >
                    <div className="flex min-w-0 flex-1 flex-col gap-2">
                        <Label htmlFor="event-search">Search events</Label>
                        <Input
                            id="event-search"
                            value={form.data.search}
                            onChange={(event) =>
                                form.setData('search', event.target.value)
                            }
                            placeholder="Search by title"
                            disabled={form.processing}
                            aria-invalid={!!form.errors.search}
                            aria-describedby="event-search-error"
                        />
                        <InputError
                            id="event-search-error"
                            message={form.errors.search}
                        />
                    </div>
                    <div className="flex min-w-40 flex-col gap-2">
                        <Label htmlFor="event-status">Status</Label>
                        <Select
                            disabled={form.processing}
                            value={form.data.status || 'all'}
                            onValueChange={(value) =>
                                form.setData(
                                    'status',
                                    value === 'all' ? '' : value,
                                )
                            }
                        >
                            <SelectTrigger id="event-status" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem value="all">
                                        All statuses
                                    </SelectItem>
                                    {invitations !== undefined ? (
                                        <SelectItem value="draft">
                                            Draft
                                        </SelectItem>
                                    ) : null}
                                    <SelectItem value="published">
                                        Published
                                    </SelectItem>
                                    <SelectItem value="cancelled">
                                        Cancelled
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.status} />
                    </div>
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={form.processing}
                    >
                        {form.processing ? (
                            <Spinner data-icon="inline-start" />
                        ) : (
                            <Search data-icon="inline-start" />
                        )}
                        Search
                    </Button>
                </form>
                {events.data.length ? (
                    <div className="grid gap-5 md:grid-cols-2 2xl:grid-cols-3">
                        {events.data.map((event) => (
                            <Card
                                key={event.id}
                                className="overflow-hidden pt-0"
                            >
                                <Link
                                    href={show(event.id)}
                                    aria-label={'View ' + event.title}
                                >
                                    <EventBanner src={event.banner_url} />
                                </Link>
                                <CardHeader>
                                    <div className="mb-2 flex flex-wrap gap-2">
                                        <Badge
                                            variant={
                                                event.status === 'cancelled'
                                                    ? 'destructive'
                                                    : 'secondary'
                                            }
                                        >
                                            {event.status === 'draft'
                                                ? 'Draft'
                                                : event.status === 'published'
                                                  ? 'Published'
                                                  : 'Cancelled'}
                                        </Badge>
                                        <Badge variant="outline">
                                            {event.owner.code}
                                        </Badge>
                                        {event.recurrence !== 'none' ? (
                                            <Badge variant="outline">
                                                Recurring
                                            </Badge>
                                        ) : null}
                                    </div>
                                    <CardTitle>
                                        <Link
                                            className="hover:underline"
                                            href={show(event.id)}
                                        >
                                            {event.title}
                                        </Link>
                                    </CardTitle>
                                    <CardDescription
                                        className="event-markdown line-clamp-2"
                                        dangerouslySetInnerHTML={{
                                            __html: event.short_description_html,
                                        }}
                                    />
                                </CardHeader>
                                <CardContent className="flex flex-col gap-3">
                                    <div className="flex gap-2 text-sm">
                                        <CalendarDays className="mt-0.5 size-4 shrink-0" />
                                        <div className="flex flex-col gap-1">
                                            {event.occurrence?.starts_at ? (
                                                <>
                                                    <p>
                                                        {eventTime(
                                                            event.occurrence
                                                                .starts_at,
                                                        )}{' '}
                                                        Z
                                                    </p>
                                                    <p className="text-muted-foreground text-xs">
                                                        {event.occurrence
                                                            .ends_at
                                                            ? 'Ends ' +
                                                              eventTime(
                                                                  event
                                                                      .occurrence
                                                                      .ends_at,
                                                              ) +
                                                              ' Z'
                                                            : null}
                                                    </p>
                                                </>
                                            ) : (
                                                <p className="text-muted-foreground">
                                                    No upcoming occurrences
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        {event.airports.map((airport) => (
                                            <Badge
                                                key={airport.id}
                                                variant="outline"
                                            >
                                                {airport.icao}
                                            </Badge>
                                        ))}
                                    </div>
                                </CardContent>
                                <CardFooter className="mt-auto">
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="w-full"
                                    >
                                        <Link href={show(event.id)}>
                                            View event
                                        </Link>
                                    </Button>
                                </CardFooter>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed px-6 py-16 text-center">
                        <CalendarDays className="text-muted-foreground size-9" />
                        <h2 className="font-semibold">
                            {filters.search || filters.status
                                ? 'No matching events'
                                : 'No events yet'}
                        </h2>
                        <p className="text-muted-foreground max-w-md text-sm">
                            {can_create
                                ? 'Create a draft to begin planning an event with your FIR.'
                                : 'Published events will appear here.'}
                        </p>
                        {can_create ? (
                            <Button asChild variant="outline">
                                <Link href={create()}>Create event</Link>
                            </Button>
                        ) : null}
                    </div>
                )}
                {events.last_page > 1 ? (
                    <nav
                        aria-label="Event pages"
                        className="flex items-center justify-between"
                    >
                        <span className="text-muted-foreground text-sm">
                            Page {events.current_page} of {events.last_page}
                        </span>
                        <div className="flex gap-2">
                            {events.current_page > 1 ? (
                                <Button asChild size="sm" variant="outline">
                                    <Link
                                        href={index({
                                            query: {
                                                ...filters,
                                                page: events.current_page - 1,
                                            },
                                        })}
                                    >
                                        <ChevronLeft />
                                        Previous
                                    </Link>
                                </Button>
                            ) : null}
                            {events.current_page < events.last_page ? (
                                <Button asChild size="sm" variant="outline">
                                    <Link
                                        href={index({
                                            query: {
                                                ...filters,
                                                page: events.current_page + 1,
                                            },
                                        })}
                                    >
                                        Next
                                        <ChevronRight />
                                    </Link>
                                </Button>
                            ) : null}
                        </div>
                    </nav>
                ) : null}
            </div>
        </>
    );
}
