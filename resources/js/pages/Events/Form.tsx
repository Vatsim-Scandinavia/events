import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useRef, useState, type FormEvent, type ReactNode } from 'react';
import { useConfirm } from '@/components/confirm-dialog';
import { DangerZone } from '@/components/events/cancellation';
import { DetailsCard } from '@/components/events/details-card';
import type { EventFormData, IntegrationField, RouteRow } from '@/components/events/event-form';
import { IntegrationFieldsCard } from '@/components/events/integration-fields-card';
import { RoutesCard } from '@/components/events/routes-card';
import { describeStaffing, removedDates, useSchedulePreview } from '@/components/events/schedule';
import { ScheduleCard } from '@/components/events/schedule-card';
import { SaveBar } from '@/components/staffing/planner/save-bar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout';
import { addDays, formatDay, toDay } from '@/lib/repeat';
import type { EditableEvent } from '@/types/event';
import type { Fir } from '@/types/inertia';

type EventFormProps = {
    event: EditableEvent | null;
    types: { id: number; name: string }[];
    firs: (Fir & { id: number })[];
    // Every IANA time zone, e.g. "Europe/Copenhagen".
    timezones: string[];
    integrationFields: IntegrationField[];
};

export default function EventForm({ event, types, firs, timezones, integrationFields }: EventFormProps) {
    const confirm = useConfirm();
    const formRef = useRef<HTMLFormElement>(null);
    const nextKey = useRef(-1);
    const newKey = () => nextKey.current--;
    const today = toDay(Date.now());
    const [startDefault, startTimeDefault] = event?.startsAt.split(' ') ?? [addDays(today, 14), '18:00'];
    const [endDefault, endTimeDefault] = event?.endsAt.split(' ') ?? [startDefault, '21:00'];

    const form = useForm<EventFormData>({
        // With a single FIR there's nothing to choose, so it's picked already.
        fir_id: event?.firId ?? (firs.length === 1 ? firs[0].id : ''),
        name: event?.name ?? '',
        event_type_id: event?.typeId ?? '',
        description: event?.description ?? '',
        summary: event?.summary ?? '',
        airports: event?.airports.join(' ') ?? '',
        routes: (event?.routes ?? []).map((route): RouteRow => ({ ...route, key: newKey() })),
        banner: null,
        remove_banner: false,
        booking_url: event?.bookingUrl ?? '',
        briefing_url: event?.briefingUrl ?? '',
        start_date: startDefault,
        start_time: startTimeDefault,
        end_date: endDefault,
        end_time: endTimeDefault,
        timezone: event?.timezone ?? 'UTC',
        repeats: event?.repeats ?? 'none',
        repeat_ends: event?.repeatUntil ? 'until' : event?.repeatCount ? 'count' : 'never',
        repeat_count: event?.repeatCount ?? 4,
        repeat_until: event?.repeatUntil ?? addDays(startDefault, 8 * 7),
        integration_fields: {},
        remove_integration_fields: [],
    });

    const { data, errors } = form;

    const payload = ({ start_date, start_time, end_date, end_time, ...values }: EventFormData) => ({
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
                form.post(`/events/${event.key}`, { preserveScroll: true });
            } else {
                form.put(`/events/${event.key}`, { preserveScroll: true });
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
            router.delete(`/events/${event.key}`);
        }
    };

    return (
        <>
            <Head title={event ? `Edit · ${event.name}` : 'New event'} />

            <div className="mx-auto flex max-w-3xl flex-col gap-12 px-4 py-8 sm:px-6">
                <form ref={formRef} onSubmit={submit} className="flex flex-col gap-8">
                    <div className="flex flex-col items-start gap-4">
                        <Button variant="ghost" size="sm" className="-ml-3" asChild>
                            <Link href={event ? `/events/${event.key}` : '/events'}>
                                <ArrowLeft />
                                {event ? 'Back to event' : 'All events'}
                            </Link>
                        </Button>
                        <h1 className="text-2xl font-semibold tracking-tight">{event ? `Edit ${event.name}` : 'New event'}</h1>
                    </div>

                    <DetailsCard form={form} firs={firs} types={types} bannerUrl={event?.bannerUrl ?? null} />

                    <ScheduleCard form={form} event={event} timezones={timezones} today={today} schedule={schedule} checkFailed={checkFailed} />

                    <Card>
                        <CardHeader>
                            <CardTitle>Slot and briefing links</CardTitle>
                            <CardDescription>
                                Optional links shown on the event page. The slot link is where pilots reserve arrival or departure slots.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-6 sm:grid-cols-2">
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

                    <RoutesCard form={form} newKey={newKey} />

                    <IntegrationFieldsCard form={form} fields={integrationFields} />

                    {/* Inside the form, so it sticks to the bottom of the screen until the form ends. */}
                    {event ? (
                        <SaveBar
                            dirty={form.isDirty}
                            saving={form.processing}
                            error={form.hasErrors ? 'Some fields need fixing. They’re marked above.' : null}
                            onSave={() => formRef.current?.requestSubmit()}
                            onDiscard={() => form.reset()}
                            discardLabel="Discard changes"
                        />
                    ) : (
                        <SaveBar
                            dirty={form.isDirty}
                            saving={form.processing}
                            error={form.hasErrors ? 'Some fields need fixing. They’re marked above.' : null}
                            status="Not created yet"
                            onSave={() => formRef.current?.requestSubmit()}
                            saveLabel="Create event"
                            canSave
                            onDiscard={() => router.visit('/events')}
                            discardLabel="Cancel"
                            discardIcon={false}
                            canDiscard
                        />
                    )}
                </form>

                {/* Last and set apart, so nobody passes a destructive action on the way to saving. */}
                {event && <DangerZone event={event} onDelete={destroy} />}
            </div>
        </>
    );
}

EventForm.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
