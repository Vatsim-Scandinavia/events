import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { AirportPicker } from '@/components/airport-picker';
import { EventBanner } from '@/components/event-banner';
import { EventField } from '@/components/event-field';
import InputError from '@/components/input-error';
import { MarkdownEditor } from '@/components/markdown-editor';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { recurrenceLabel, updateEventStart } from '@/lib/event-time';
import { index, show, store, update } from '@/routes/events';
import type { Airport, Fir, ManagedEvent } from '@/types/events';

type EventFormData = {
    roster_enabled: boolean;
    owner_team_id: string;
    title: string;
    short_description: string;
    description: string;
    airport_ids: number[];
    timezone: string;
    local_start: string;
    local_end: string;
    recurrence: 'none' | 'weekly' | 'monthly';
    recurrence_interval: number;
    monthly_week: number;
    recurrence_until: string;
    banner: File | null;
    remove_banner: boolean;
};

const recurrenceOptions = [
    {
        value: 'none',
        label: 'Does not repeat',
        recurrence: 'none',
        interval: 1,
    },
    { value: 'weekly', label: 'Weekly', recurrence: 'weekly', interval: 1 },
    {
        value: 'biweekly',
        label: 'Bi-weekly (every 2 weeks)',
        recurrence: 'weekly',
        interval: 2,
    },
    { value: 'monthly', label: 'Monthly', recurrence: 'monthly', interval: 1 },
    {
        value: 'quarterly',
        label: 'Quarterly (every 3 months)',
        recurrence: 'monthly',
        interval: 3,
    },
] as const;

export default function EventForm({
    event,
    firs,
    timezones,
}: {
    event: ManagedEvent | null;
    firs: Fir[];
    timezones: string[];
}) {
    const form = useForm<EventFormData>({
        roster_enabled: event?.roster_enabled ?? false,
        owner_team_id: String(event?.owner_team_id ?? firs[0]?.id ?? ''),
        title: event?.title ?? '',
        short_description: event?.short_description ?? '',
        description: event?.description ?? '',
        airport_ids: event?.airports.map((airport) => airport.id) ?? [],
        timezone: event?.timezone ?? 'UTC',
        local_start: event?.local_start ?? '',
        local_end: event?.local_end ?? '',
        recurrence: event?.recurrence ?? 'none',
        recurrence_interval: event?.recurrence_interval ?? 1,
        monthly_week: event?.monthly_week ?? 1,
        recurrence_until: event?.recurrence_until ?? '',
        banner: null,
        remove_banner: false,
    });
    const [airports, setAirports] = useState<Airport[]>(event?.airports ?? []);
    const [preview, setPreview] = useState<string | null>(null);
    const scheduleLockedByCancellations =
        event?.schedule_locked_by_cancellations ?? false;
    const rosterToggleLocked = event?.roster_toggle_locked ?? false;
    const locked =
        scheduleLockedByCancellations ||
        (!!event?.roster_exists && form.data.roster_enabled);
    const recurrenceOption = recurrenceOptions.find(
        (option) =>
            option.recurrence === form.data.recurrence &&
            (option.recurrence === 'none' ||
                option.interval === form.data.recurrence_interval),
    );
    useEffect(() => {
        if (!form.data.banner) {
            setPreview(null);
            return;
        }
        const url = URL.createObjectURL(form.data.banner);
        setPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [form.data.banner]);
    const back = event ? show(event.id) : index();
    const control = (field: keyof EventFormData) => ({
        'aria-invalid': !!form.errors[field],
        'aria-describedby': field + '-error',
        disabled: form.processing,
    });
    return (
        <>
            <Head title={event ? 'Edit event' : 'Create event'} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-8">
                <header className="flex flex-col gap-3">
                    <Link
                        href={back}
                        className="text-muted-foreground flex w-fit items-center gap-2 text-sm hover:underline"
                    >
                        <ArrowLeft className="size-4" />
                        {event ? 'Back to event' : 'All events'}
                    </Link>
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {event ? 'Edit event' : 'Create event'}
                        </h1>
                        <p className="text-muted-foreground mt-2 text-sm">
                            Save a private draft for your FIR and its accepted
                            collaborators.
                        </p>
                    </div>
                </header>
                <form
                    className="flex flex-col gap-6"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.transform((data) =>
                            event ? { ...data, _method: 'put' } : data,
                        );
                        form.post(event ? update.url(event.id) : store.url(), {
                            onSuccess: () =>
                                toast.success(
                                    event ? 'Event updated.' : 'Draft created.',
                                ),
                        });
                    }}
                >
                    {form.hasErrors ? (
                        <Alert variant="destructive">
                            <AlertDescription>
                                <ul className="list-inside list-disc">
                                    {Object.entries(form.errors).map(
                                        ([key, error]) => (
                                            <li key={key}>{error}</li>
                                        ),
                                    )}
                                </ul>
                            </AlertDescription>
                        </Alert>
                    ) : null}
                    <div className="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
                        <div className="flex min-w-0 flex-col gap-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Event details</CardTitle>
                                    <CardDescription>
                                        Introduce the event and tell
                                        participants what to expect.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-5">
                                    <EventField
                                        id="title"
                                        label="Title"
                                        error={form.errors.title}
                                    >
                                        <Input
                                            id="title"
                                            value={form.data.title}
                                            onChange={(e) =>
                                                form.setData(
                                                    'title',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Sunday evening in Scandinavia"
                                            maxLength={255}
                                            required
                                            {...control('title')}
                                        />
                                    </EventField>
                                    <EventField
                                        id="short_description"
                                        label="Short description"
                                        error={form.errors.short_description}
                                        hint="A brief summary for the event listing. Up to 500 characters."
                                    >
                                        <MarkdownEditor
                                            id="short_description"
                                            value={form.data.short_description}
                                            onChange={(value) =>
                                                form.setData(
                                                    'short_description',
                                                    value,
                                                )
                                            }
                                            maxLength={500}
                                            rows={3}
                                            required
                                            {...control('short_description')}
                                        />
                                    </EventField>
                                    <EventField
                                        id="description"
                                        label="Description"
                                        error={form.errors.description}
                                        hint="Markdown supported: headings, **bold**, lists, links, and tables."
                                    >
                                        <MarkdownEditor
                                            id="description"
                                            value={form.data.description}
                                            onChange={(value) =>
                                                form.setData(
                                                    'description',
                                                    value,
                                                )
                                            }
                                            maxLength={50000}
                                            rows={10}
                                            required
                                            {...control('description')}
                                        />
                                    </EventField>
                                    <AirportPicker
                                        airports={airports}
                                        onChange={(selected) => {
                                            setAirports(selected);
                                            form.setData(
                                                'airport_ids',
                                                selected.map(
                                                    (airport) => airport.id,
                                                ),
                                            );
                                        }}
                                        disabled={form.processing}
                                        error={form.errors.airport_ids}
                                    />
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Schedule</CardTitle>
                                    <CardDescription>
                                        Enter local dates and times. Event
                                        listings display UTC / Zulu by default.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-5">
                                    <div
                                        className="flex items-start gap-3"
                                        data-invalid={
                                            !!form.errors.roster_enabled
                                        }
                                    >
                                        <Checkbox
                                            id="roster_enabled"
                                            checked={form.data.roster_enabled}
                                            onCheckedChange={(checked) =>
                                                form.setData(
                                                    'roster_enabled',
                                                    checked === true,
                                                )
                                            }
                                            disabled={
                                                form.processing ||
                                                rosterToggleLocked
                                            }
                                            aria-invalid={
                                                !!form.errors.roster_enabled
                                            }
                                            aria-describedby="roster_enabled-hint roster_enabled-error"
                                        />
                                        <div className="flex flex-col gap-2">
                                            <Label htmlFor="roster_enabled">
                                                Use a roster
                                            </Label>
                                            <p
                                                id="roster_enabled-hint"
                                                className="text-muted-foreground text-xs"
                                            >
                                                {rosterToggleLocked
                                                    ? 'Withdraw all bookings and interest submissions before turning the roster off.'
                                                    : event?.roster_exists &&
                                                        (!event.roster_enabled ||
                                                            !form.data
                                                                .roster_enabled)
                                                      ? 'Your saved setup is kept. Re-enabling keeps signups closed until you review and open the roster.'
                                                      : 'Set up positions or collect controller interest after saving. The roster is reused for each occurrence.'}
                                            </p>
                                            <InputError
                                                id="roster_enabled-error"
                                                message={
                                                    form.errors.roster_enabled
                                                }
                                            />
                                        </div>
                                    </div>
                                    {locked ? (
                                        <Alert>
                                            <AlertDescription>
                                                {scheduleLockedByCancellations
                                                    ? 'This event has cancelled occurrences. Its schedule is fixed to preserve them.'
                                                    : rosterToggleLocked
                                                      ? 'The schedule is fixed while this roster has bookings or interest submissions.'
                                                      : 'The schedule is fixed while the saved roster is enabled. Turn off Use a roster to change the schedule.'}
                                            </AlertDescription>
                                        </Alert>
                                    ) : null}
                                    <EventField
                                        id="timezone"
                                        label="Schedule timezone"
                                        error={form.errors.timezone}
                                        hint="Use a region such as Europe/Copenhagen to keep the same local time when daylight saving changes."
                                    >
                                        <Input
                                            id="timezone"
                                            list="event-timezones"
                                            value={form.data.timezone}
                                            onChange={(e) =>
                                                form.setData(
                                                    'timezone',
                                                    e.target.value,
                                                )
                                            }
                                            autoComplete="off"
                                            required
                                            {...control('timezone')}
                                            disabled={locked || form.processing}
                                        />
                                        <datalist id="event-timezones">
                                            {timezones.map((timezone) => (
                                                <option
                                                    key={timezone}
                                                    value={timezone}
                                                />
                                            ))}
                                        </datalist>
                                    </EventField>
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <EventField
                                            id="local_start"
                                            label="Start date and time"
                                            error={form.errors.local_start}
                                        >
                                            <Input
                                                id="local_start"
                                                type="datetime-local"
                                                value={form.data.local_start}
                                                onChange={(e) => {
                                                    const localStart =
                                                        e.target.value;
                                                    form.setData((data) =>
                                                        updateEventStart(
                                                            data,
                                                            localStart,
                                                        ),
                                                    );
                                                }}
                                                required
                                                {...control('local_start')}
                                                disabled={
                                                    locked || form.processing
                                                }
                                            />
                                        </EventField>
                                        <EventField
                                            id="local_end"
                                            label="End date and time"
                                            error={form.errors.local_end}
                                        >
                                            <Input
                                                id="local_end"
                                                type="datetime-local"
                                                value={form.data.local_end}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'local_end',
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                                {...control('local_end')}
                                                disabled={
                                                    locked || form.processing
                                                }
                                            />
                                        </EventField>
                                    </div>
                                    <EventField
                                        id="recurrence"
                                        label="Repeats"
                                        error={
                                            form.errors.recurrence ??
                                            form.errors.recurrence_interval
                                        }
                                    >
                                        <Select
                                            value={
                                                recurrenceOption?.value ??
                                                'current'
                                            }
                                            disabled={locked || form.processing}
                                            onValueChange={(value) => {
                                                const option =
                                                    recurrenceOptions.find(
                                                        (option) =>
                                                            option.value ===
                                                            value,
                                                    );
                                                if (option) {
                                                    form.setData((data) => ({
                                                        ...data,
                                                        recurrence:
                                                            option.recurrence,
                                                        recurrence_interval:
                                                            option.interval,
                                                    }));
                                                }
                                            }}
                                        >
                                            <SelectTrigger
                                                id="recurrence"
                                                className="w-full"
                                                aria-invalid={
                                                    !!(
                                                        form.errors
                                                            .recurrence ??
                                                        form.errors
                                                            .recurrence_interval
                                                    )
                                                }
                                                aria-describedby="recurrence-error"
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {!recurrenceOption ? (
                                                        <SelectItem
                                                            value="current"
                                                            disabled
                                                        >
                                                            {recurrenceLabel(
                                                                form.data,
                                                            )}{' '}
                                                            (current schedule)
                                                        </SelectItem>
                                                    ) : null}
                                                    {recurrenceOptions.map(
                                                        (option) => (
                                                            <SelectItem
                                                                key={
                                                                    option.value
                                                                }
                                                                value={
                                                                    option.value
                                                                }
                                                            >
                                                                {option.label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    </EventField>
                                    {form.data.recurrence === 'monthly' ? (
                                        <EventField
                                            id="monthly_week"
                                            label="Weekday position in the month"
                                            error={form.errors.monthly_week}
                                            hint="The position must match the first start date. Months without a fifth occurrence are skipped."
                                        >
                                            <Select
                                                value={String(
                                                    form.data.monthly_week,
                                                )}
                                                onValueChange={(value) =>
                                                    form.setData(
                                                        'monthly_week',
                                                        Number(value),
                                                    )
                                                }
                                                disabled={
                                                    locked || form.processing
                                                }
                                            >
                                                <SelectTrigger
                                                    id="monthly_week"
                                                    className="w-full"
                                                    aria-invalid={
                                                        !!form.errors
                                                            .monthly_week
                                                    }
                                                    aria-describedby="monthly_week-error"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        {[
                                                            [1, 'First'],
                                                            [2, 'Second'],
                                                            [3, 'Third'],
                                                            [4, 'Fourth'],
                                                            [5, 'Fifth'],
                                                            [-1, 'Last'],
                                                        ].map(
                                                            ([
                                                                value,
                                                                label,
                                                            ]) => (
                                                                <SelectItem
                                                                    key={value}
                                                                    value={String(
                                                                        value,
                                                                    )}
                                                                >
                                                                    {label}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                        </EventField>
                                    ) : null}
                                    {form.data.recurrence !== 'none' ? (
                                        <>
                                            <p
                                                className="text-sm font-medium"
                                                aria-live="polite"
                                            >
                                                {recurrenceLabel(form.data)}
                                            </p>
                                            <EventField
                                                id="recurrence_until"
                                                label="Repeat through (optional)"
                                                error={
                                                    form.errors.recurrence_until
                                                }
                                                hint="Inclusive, in the schedule timezone. Leave empty to repeat indefinitely."
                                            >
                                                <Input
                                                    id="recurrence_until"
                                                    type="date"
                                                    value={
                                                        form.data
                                                            .recurrence_until
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'recurrence_until',
                                                            e.target.value,
                                                        )
                                                    }
                                                    {...control(
                                                        'recurrence_until',
                                                    )}
                                                    disabled={
                                                        locked ||
                                                        form.processing
                                                    }
                                                />
                                            </EventField>
                                            <p className="text-muted-foreground text-xs">
                                                During clock changes, repeated
                                                local times use the first
                                                occurrence. Dates with a
                                                nonexistent start or end time
                                                are marked as skipped.
                                            </p>
                                        </>
                                    ) : null}
                                </CardContent>
                            </Card>
                        </div>
                        <div className="flex min-w-0 flex-col gap-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Owner FIR</CardTitle>
                                    <CardDescription>
                                        Your FIR owns this event and controls
                                        collaboration access.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <EventField
                                        id="owner_team_id"
                                        label="FIR"
                                        error={form.errors.owner_team_id}
                                    >
                                        <Select
                                            value={form.data.owner_team_id}
                                            disabled={
                                                !!event || form.processing
                                            }
                                            onValueChange={(value) =>
                                                form.setData(
                                                    'owner_team_id',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger
                                                id="owner_team_id"
                                                className="w-full"
                                                aria-invalid={
                                                    !!form.errors.owner_team_id
                                                }
                                                aria-describedby="owner_team_id-error"
                                            >
                                                <SelectValue placeholder="Choose FIR" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {firs.map((fir) => (
                                                        <SelectItem
                                                            key={fir.id}
                                                            value={String(
                                                                fir.id,
                                                            )}
                                                        >
                                                            {fir.code} ·{' '}
                                                            {fir.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    </EventField>
                                    {firs.length === 0 ? (
                                        <p className="text-destructive mt-3 text-sm">
                                            Create an FIR before adding events.
                                        </p>
                                    ) : null}
                                </CardContent>
                            </Card>
                            <Card className="overflow-hidden">
                                <CardHeader>
                                    <CardTitle>Banner</CardTitle>
                                    <CardDescription>
                                        Optional. The default banner is used
                                        when no image is uploaded.
                                    </CardDescription>
                                </CardHeader>
                                <EventBanner
                                    src={
                                        preview ??
                                        (form.data.remove_banner
                                            ? null
                                            : event?.banner_url)
                                    }
                                />
                                <CardContent className="flex flex-col gap-4">
                                    <EventField
                                        id="banner"
                                        label="Upload banner"
                                        error={form.errors.banner}
                                        hint="JPG, PNG or WebP, up to 5 MB. A wide image works best."
                                    >
                                        <Input
                                            id="banner"
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                            onChange={(e) => {
                                                form.setData(
                                                    'banner',
                                                    e.target.files?.[0] ?? null,
                                                );
                                                form.setData(
                                                    'remove_banner',
                                                    false,
                                                );
                                            }}
                                            {...control('banner')}
                                        />
                                    </EventField>
                                    {event?.banner_url && !form.data.banner ? (
                                        <div className="flex items-center gap-2">
                                            <Checkbox
                                                id="remove_banner"
                                                checked={
                                                    form.data.remove_banner
                                                }
                                                onCheckedChange={(checked) =>
                                                    form.setData(
                                                        'remove_banner',
                                                        checked === true,
                                                    )
                                                }
                                                disabled={form.processing}
                                            />
                                            <Label htmlFor="remove_banner">
                                                Use the default banner
                                            </Label>
                                        </div>
                                    ) : null}
                                    {form.progress ? (
                                        <progress
                                            className="w-full"
                                            value={form.progress.percentage}
                                            max={100}
                                            aria-label="Banner upload progress"
                                        />
                                    ) : null}
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                    <div className="flex flex-wrap justify-end gap-3">
                        <Button asChild variant="outline">
                            <Link href={back}>Discard changes</Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing || firs.length === 0}
                        >
                            {form.processing ? (
                                <Spinner />
                            ) : (
                                <Save data-icon="inline-start" />
                            )}
                            {event ? 'Save changes' : 'Save draft'}
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
