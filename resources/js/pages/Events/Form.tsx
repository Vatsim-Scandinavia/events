import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2, X } from 'lucide-react';
import { useRef, useState, type FormEvent, type ReactNode } from 'react';
import { useConfirm } from '@/components/confirm-dialog';
import { BannerInput } from '@/components/events/banner-input';
import { Cancellation } from '@/components/events/cancellation';
import { DescriptionInput } from '@/components/events/description-input';
import { DatePicker, DateTimePicker } from '@/components/events/date-inputs';
import { describeStaffing, removedDates, ScheduleWarnings, useSchedulePreview, type RepeatEnds } from '@/components/events/schedule';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { TimezoneSelect } from '@/components/timezone-select';
import AppLayout from '@/layouts/AppLayout';
import { addDays, daysBetween, fitting, formatDay, MAX_DATES, options as repeatOptions, toDay, type RepeatInterval } from '@/lib/repeat';
import type { EditableEvent, EventRoute } from '@/types/event';
import type { Fir } from '@/types/inertia';

type EventFormProps = {
    event: EditableEvent | null;
    types: { id: number; name: string }[];
    firs: (Fir & { id: number })[];
    // Every IANA time zone, e.g. "Europe/Copenhagen".
    timezones: string[];
};

type RouteRow = EventRoute & { key: number };

export default function EventForm({ event, types, firs, timezones }: EventFormProps) {
    const confirm = useConfirm();
    const nextKey = useRef(-1);
    const newKey = () => nextKey.current--;
    const today = toDay(Date.now());
    const [startDefault, startTimeDefault] = event?.startsAt.split(' ') ?? [addDays(today, 14), '18:00'];
    const [endDefault, endTimeDefault] = event?.endsAt.split(' ') ?? [startDefault, '21:00'];

    const form = useForm({
        // With a single FIR there's nothing to choose, so it's picked already.
        fir_id: event?.firId ?? (firs.length === 1 ? firs[0].id : ('' as number | '')),
        name: event?.name ?? '',
        event_type_id: event?.typeId ?? ('' as number | ''),
        description: event?.description ?? '',
        airports: event?.airports.join(' ') ?? '',
        routes: (event?.routes ?? []).map((route): RouteRow => ({ ...route, key: newKey() })),
        banner: null as File | null,
        remove_banner: false,
        booking_url: event?.bookingUrl ?? '',
        briefing_url: event?.briefingUrl ?? '',
        start_date: startDefault,
        start_time: startTimeDefault,
        end_date: endDefault,
        end_time: endTimeDefault,
        timezone: event?.timezone ?? 'UTC',
        repeats: event?.repeats ?? ('none' as RepeatInterval),
        repeat_ends: (event?.repeatUntil ? 'until' : event?.repeatCount ? 'count' : 'never') as RepeatEnds,
        repeat_count: event?.repeatCount ?? 4,
        repeat_until: event?.repeatUntil ?? addDays(startDefault, 8 * 7),
    });

    const { data, errors } = form;
    const error = (key: string) => (errors as Record<string, string | undefined>)[key];
    const firstError = (prefix: string) => Object.entries(errors).find(([key]) => key.startsWith(prefix))?.[1] as string | undefined;

    const payload = ({ start_date, start_time, end_date, end_time, ...values }: typeof data) => ({
        ...values,
        starts_at: `${start_date} ${start_time}`,
        ends_at: `${end_date} ${end_time}`,
        airports: values.airports.split(/[\s,;]+/).filter(Boolean),
        routes: values.routes.map(({ from, to, route, mandatory }) => ({ from, to, route, mandatory: !!mandatory })),
    });

    form.transform(payload);

    const schedule = useSchedulePreview(event, {
        starts_at: `${data.start_date} ${data.start_time}`,
        ends_at: `${data.end_date} ${data.end_time}`,
        timezone: data.timezone,
        repeats: data.repeats,
        repeat_ends: data.repeat_ends,
        repeat_count: data.repeat_count,
        repeat_until: data.repeat_until,
    });
    const zoneLabel = data.timezone === 'UTC' ? 'UTC' : data.timezone.replaceAll('_', ' ');
    // The preview's errors are the freshest; once it accepts the schedule, errors from the last save are stale.
    const scheduleError = (key: string) => schedule.errors[key] ?? (schedule.preview ? undefined : error(key));
    const [checkFailed, setCheckFailed] = useState(false);

    const submit = async (e: FormEvent) => {
        e.preventDefault();

        if (event) {
            // Ask about deleted dates from the schedule as it is now, never from a preview still on its way.
            const plan = await schedule.latest();
            setCheckFailed(plan === null);

            if (plan === null) {
                return;
            }

            const removed = plan === 'invalid' ? [] : removedDates(event, plan);
            const losing = describeStaffing(removed);

            if (
                losing &&
                !(await confirm({
                    title: `Remove ${removed.length === 1 ? 'a date' : 'dates'}?`,
                    description: `With this schedule, ${removed.map((date) => formatDay(date.date)).join(', ')} ${removed.length === 1 ? 'is' : 'are'} no longer needed. ${removed.length === 1 ? 'It has' : 'They have'} ${losing}, which will be deleted. This can’t be undone.`,
                    confirmLabel: 'Remove and save',
                    destructive: true,
                }))
            ) {
                return;
            }

            // Files can only be sent as multipart, which Laravel reads on POST, so the PUT is spoofed.
            if (data.banner) {
                form.transform((values) => ({ ...payload(values), _method: 'put' }));
                form.post(`/events/${event.id}`, { preserveScroll: true });
            } else {
                form.put(`/events/${event.id}`, { preserveScroll: true });
            }
        } else {
            form.post('/events', { preserveScroll: true });
        }
    };

    const destroy = async () => {
        if (!event) {
            return;
        }

        const losing = describeStaffing(event.dates);
        const confirmed = await confirm({
            title: `Delete ${event.name}?`,
            description: `The event, all of its dates, positions and shifts${losing ? `, and ${losing}` : ''} will be deleted. This can’t be undone.`,
            confirmLabel: 'Delete event',
            destructive: true,
        });

        if (confirmed) {
            router.delete(`/events/${event.id}`);
        }
    };

    // A series that's under way keeps its first date; only its time and repeats can change.
    const underWay = event !== null && event.dates.some((date) => date.hasStarted && date.date === startDefault);

    // Moving the start moves the end with it, so the length stays the same.
    const pickStartDate = (day: string) =>
        form.setData((values) => ({
            ...values,
            start_date: day,
            end_date: addDays(day, daysBetween(values.start_date, values.end_date)),
            repeats: fitting(values.repeats, day),
            repeat_until: values.repeat_until < day ? addDays(day, 8 * 7) : values.repeat_until,
        }));

    return (
        <>
            <Head title={event ? `Edit · ${event.name}` : 'New event'} />

            <form onSubmit={submit} className="mx-auto flex max-w-3xl flex-col gap-8 px-4 py-8 sm:px-6">
                <div className="flex flex-col gap-3">
                    <Button variant="ghost" size="sm" className="-ml-2.5 self-start" asChild>
                        <Link href={event ? `/events/${event.id}` : '/events'}>
                            <ArrowLeft />
                            {event ? 'Back to event' : 'All events'}
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold tracking-tight">{event ? `Edit ${event.name}` : 'New event'}</h1>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-5">
                        <div className="grid gap-5 sm:grid-cols-[1fr_14rem]">
                            <Field label="Name" error={errors.name}>
                                <Input
                                    value={data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    aria-invalid={!!errors.name}
                                    required
                                />
                            </Field>
                            <Field label="Run by" error={errors.fir_id}>
                                {/* Staff of a single FIR have nothing to choose. */}
                                {firs.length === 1 && data.fir_id === firs[0].id ? (
                                    <span className="flex h-9 items-center font-medium">
                                        {firs[0].icao} {firs[0].name !== firs[0].icao ? firs[0].name : ''}
                                    </span>
                                ) : (
                                    <NativeSelect
                                        value={data.fir_id}
                                        onChange={(e) => form.setData('fir_id', e.target.value === '' ? '' : Number(e.target.value))}
                                        aria-invalid={!!errors.fir_id}
                                        required
                                    >
                                        <option value="" disabled>
                                            {firs.length === 0 ? 'No FIRs set up yet' : 'Pick a FIR'}
                                        </option>
                                        {firs.map((fir) => (
                                            <option key={fir.id} value={fir.id}>
                                                {fir.icao} {fir.name !== fir.icao ? fir.name : ''}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                )}
                            </Field>
                        </div>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field label="Type" error={errors.event_type_id}>
                                <NativeSelect
                                    value={data.event_type_id}
                                    onChange={(e) => form.setData('event_type_id', e.target.value === '' ? '' : Number(e.target.value))}
                                    aria-invalid={!!errors.event_type_id}
                                >
                                    <option value="">General</option>
                                    {types.map((type) => (
                                        <option key={type.id} value={type.id}>
                                            {type.name}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </Field>
                            <Field label="Airports" error={errors.airports ?? firstError('airports.')}>
                                <Input
                                    value={data.airports}
                                    onChange={(e) => form.setData('airports', e.target.value.toUpperCase())}
                                    placeholder="EKCH EKBI"
                                    aria-invalid={!!(errors.airports ?? firstError('airports.'))}
                                    className="font-mono"
                                />
                            </Field>
                        </div>
                        <DescriptionInput
                            value={data.description}
                            error={errors.description}
                            onChange={(markdown) => form.setData('description', markdown)}
                        />
                        <BannerInput
                            current={data.remove_banner ? null : (event?.bannerUrl ?? null)}
                            file={data.banner}
                            error={errors.banner}
                            onChange={(file) => form.setData('banner', file)}
                            onRemove={() => {
                                form.setData((values) => ({ ...values, banner: null, remove_banner: values.banner ? values.remove_banner : true }));
                            }}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>When</CardTitle>
                        <CardDescription>
                            Set the first date; repeats start and end at the same local time. In a zone with daylight saving time, repeats keep that
                            local time when the clocks change.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-5">
                        <Field label="Time zone" error={scheduleError('timezone')} className="sm:max-w-xs">
                            <TimezoneSelect timezones={timezones} value={data.timezone} onChange={(e) => form.setData('timezone', e.target.value)} />
                        </Field>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="flex flex-col gap-1.5">
                                <span className="text-sm font-medium">Starts ({zoneLabel})</span>
                                <DateTimePicker
                                    label="Starts"
                                    date={data.start_date}
                                    time={data.start_time}
                                    min={today}
                                    dateLocked={underWay}
                                    onDateChange={pickStartDate}
                                    onTimeChange={(time) => form.setData('start_time', time)}
                                />
                                <FieldError message={scheduleError('starts_at')} />
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <span className="text-sm font-medium">Ends ({zoneLabel})</span>
                                <DateTimePicker
                                    label="Ends"
                                    date={data.end_date}
                                    time={data.end_time}
                                    min={data.start_date}
                                    onDateChange={(day) => form.setData('end_date', day)}
                                    onTimeChange={(time) => form.setData('end_time', time)}
                                />
                                <FieldError message={scheduleError('ends_at')} />
                            </div>
                        </div>

                        <div className="grid gap-5 sm:grid-cols-2">
                            <div className="flex flex-col gap-1.5">
                                <span className="text-sm font-medium">Repeats</span>
                                <NativeSelect
                                    aria-label="Repeats"
                                    value={data.repeats}
                                    onChange={(e) => form.setData('repeats', e.target.value as RepeatInterval)}
                                >
                                    {repeatOptions(data.start_date).map((option) => (
                                        <option key={option.value} value={option.value}>
                                            {option.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                                <FieldError message={scheduleError('repeats')} />
                            </div>
                            {data.repeats !== 'none' && (
                                <div className="flex flex-col gap-1.5">
                                    <span className="text-sm font-medium">Ends</span>
                                    <div className="flex items-center gap-2">
                                        <div className="w-28 shrink-0">
                                            <NativeSelect
                                                aria-label="Ends"
                                                value={data.repeat_ends}
                                                onChange={(e) => form.setData('repeat_ends', e.target.value as RepeatEnds)}
                                            >
                                                <option value="never">Never</option>
                                                <option value="count">After</option>
                                                <option value="until">On</option>
                                            </NativeSelect>
                                        </div>
                                        {data.repeat_ends === 'count' && (
                                            <>
                                                <Input
                                                    type="number"
                                                    aria-label="Number of dates"
                                                    min={2}
                                                    max={MAX_DATES}
                                                    className="w-20"
                                                    value={data.repeat_count}
                                                    onChange={(e) => form.setData('repeat_count', Number(e.target.value))}
                                                />
                                                <span className="text-sm text-muted-foreground">dates</span>
                                            </>
                                        )}
                                        {data.repeat_ends === 'until' && (
                                            <DatePicker
                                                label="Last date"
                                                value={data.repeat_until}
                                                min={data.start_date}
                                                onChange={(day) => form.setData('repeat_until', day)}
                                            />
                                        )}
                                    </div>
                                    <FieldError
                                        message={scheduleError('repeat_count') ?? scheduleError('repeat_until') ?? scheduleError('repeat_ends')}
                                    />
                                </div>
                            )}
                        </div>

                        {event && schedule.preview && <ScheduleWarnings event={event} preview={schedule.preview} />}
                        {checkFailed && <FieldError message="The new dates couldn’t be checked, so nothing was saved. Try again." />}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Slot and briefing links</CardTitle>
                        <CardDescription>
                            Optional links shown on the event page. The slot link is where pilots reserve arrival or departure slots.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5 sm:grid-cols-2">
                        <Field label="Slot link" error={errors.booking_url}>
                            <Input
                                type="url"
                                value={data.booking_url}
                                onChange={(e) => form.setData('booking_url', e.target.value)}
                                placeholder="https://"
                                aria-invalid={!!errors.booking_url}
                            />
                        </Field>
                        <Field label="Briefing link" error={errors.briefing_url}>
                            <Input
                                type="url"
                                value={data.briefing_url}
                                onChange={(e) => form.setData('briefing_url', e.target.value)}
                                placeholder="https://"
                                aria-invalid={!!errors.briefing_url}
                            />
                        </Field>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Suggested routes</CardTitle>
                        <CardDescription>Pilots can copy these from the event page.</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        {data.routes.map((route, index) => {
                            const set = (change: Partial<EventRoute>) =>
                                form.setData(
                                    'routes',
                                    data.routes.map((item) => (item.key === route.key ? { ...item, ...change } : item)),
                                );
                            const rowError = error(`routes.${index}.from`) ?? error(`routes.${index}.to`) ?? error(`routes.${index}.route`);

                            return (
                                <div key={route.key} className="flex flex-col gap-2 rounded-lg p-3 ring-1 ring-foreground/10">
                                    <div className="flex items-center gap-2">
                                        <Input
                                            aria-label="From"
                                            placeholder="From"
                                            className="w-24 font-mono"
                                            maxLength={4}
                                            value={route.from}
                                            onChange={(e) => set({ from: e.target.value.toUpperCase() })}
                                        />
                                        <span className="text-muted-foreground">→</span>
                                        <Input
                                            aria-label="To"
                                            placeholder="To"
                                            className="w-24 font-mono"
                                            maxLength={4}
                                            value={route.to}
                                            onChange={(e) => set({ to: e.target.value.toUpperCase() })}
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="ml-auto"
                                            aria-label="Remove route"
                                            onClick={() =>
                                                form.setData(
                                                    'routes',
                                                    data.routes.filter((item) => item.key !== route.key),
                                                )
                                            }
                                        >
                                            <X />
                                        </Button>
                                    </div>
                                    <Textarea
                                        aria-label="Route"
                                        placeholder="TUDLO DCT ..."
                                        className="min-h-10 font-mono text-xs"
                                        value={route.route}
                                        onChange={(e) => set({ route: e.target.value.toUpperCase() })}
                                    />
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox checked={!!route.mandatory} onCheckedChange={(checked) => set({ mandatory: checked === true })} />
                                        Mandatory route
                                    </label>
                                    <FieldError message={rowError} />
                                </div>
                            );
                        })}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="self-start"
                            onClick={() => form.setData('routes', [...data.routes, { key: newKey(), from: '', to: '', route: '', mandatory: false }])}
                        >
                            <Plus />
                            Add a route
                        </Button>
                    </CardContent>
                </Card>

                {event && <Cancellation event={event} />}

                <div className="flex flex-wrap items-center gap-3">
                    <Button type="submit" disabled={form.processing || (event !== null && !form.isDirty)}>
                        {event ? 'Save changes' : 'Create event'}
                    </Button>
                    <Button variant="ghost" asChild>
                        <Link href={event ? `/events/${event.id}` : '/events'}>Cancel</Link>
                    </Button>
                    {event && (
                        <Button type="button" variant="destructive" className="sm:ml-auto" onClick={destroy}>
                            <Trash2 />
                            Delete event
                        </Button>
                    )}
                </div>
            </form>
        </>
    );
}

EventForm.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
