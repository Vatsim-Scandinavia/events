import { DatePicker, DateTimePicker } from '@/components/events/date-inputs';
import { errorFor, type EventForm } from '@/components/events/event-form';
import { ScheduleWarnings, useSchedulePreview, type RepeatEnds } from '@/components/events/schedule';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { TimezoneSelect } from '@/components/timezone-select';
import { addDays, daysBetween, fitting, MAX_DATES, options as repeatOptions, type RepeatInterval } from '@/lib/repeat';
import type { EditableEvent } from '@/types/event';

// When the event takes place: its first date, time zone and how it repeats, with warnings about
// what the new schedule would move or delete.
export function ScheduleCard({
    form,
    event,
    timezones,
    today,
    schedule,
    checkFailed,
}: {
    form: EventForm;
    event: EditableEvent | null;
    timezones: string[];
    // The earliest date the event can start on.
    today: string;
    schedule: ReturnType<typeof useSchedulePreview>;
    checkFailed: boolean;
}) {
    const { data } = form;
    const zoneLabel = data.timezone === 'UTC' ? 'UTC' : data.timezone.replaceAll('_', ' ');
    // The preview's errors are the freshest; once it accepts the schedule, errors from the last save are stale.
    const scheduleError = (key: string) => schedule.errors[key] ?? (schedule.preview ? undefined : errorFor(form, key));

    // A series that's under way keeps its first date; only its time and repeats can change.
    const firstDate = event?.startsAt.split(' ')[0];
    const underWay = event !== null && event.dates.some((date) => date.hasStarted && date.date === firstDate);

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
        <Card>
            <CardHeader>
                <CardTitle>When</CardTitle>
                <CardDescription>
                    Set the first date; repeats start and end at the same local time. In a zone with daylight saving time, repeats keep that local
                    time when the clocks change.
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
                            <FieldError message={scheduleError('repeat_count') ?? scheduleError('repeat_until') ?? scheduleError('repeat_ends')} />
                        </div>
                    )}
                </div>

                {event && schedule.preview && <ScheduleWarnings event={event} preview={schedule.preview} />}
                {checkFailed && <FieldError message="The new dates couldn’t be checked, so nothing was saved. Try again." />}
            </CardContent>
        </Card>
    );
}
