import { Head, useForm } from '@inertiajs/react';
import { LocateFixed } from 'lucide-react';
import { useSyncExternalStore, type FormEvent, type ReactNode } from 'react';
import { TimezoneSelect } from '@/components/timezone-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import AppLayout from '@/layouts/AppLayout';
import { clockIn, describeTimezone } from '@/lib/time';

// Mirrors App\Http\Controllers\SettingsController@edit.
type SettingsProps = {
    settings: {
        timezone: string;
    };
    // Every IANA time zone, e.g. "Europe/Copenhagen".
    timezones: string[];
};

const noSubscription = () => () => {};

// What only the browser knows, null when rendered on the server.
const useBrowserTimezone = () =>
    useSyncExternalStore(
        noSubscription,
        () => Intl.DateTimeFormat().resolvedOptions().timeZone,
        () => null,
    );

const useTodayAtSixZulu = () =>
    useSyncExternalStore(
        noSubscription,
        () => `${new Date().toISOString().slice(0, 10)}T18:00:00Z`,
        () => null,
    );

export default function Settings({ settings, timezones }: SettingsProps) {
    const form = useForm({ timezone: settings.timezone });
    const browserTimezone = useBrowserTimezone();
    const sample = useTodayAtSixZulu();
    const canUseBrowserTimezone = browserTimezone !== null && browserTimezone !== form.data.timezone && timezones.includes(browserTimezone);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put('/settings', { preserveScroll: true });
    };

    return (
        <>
            <Head title="Settings" />

            <div className="mx-auto flex max-w-3xl flex-col gap-8 px-4 py-8 sm:px-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">Settings</h1>
                    <p className="text-muted-foreground">Choose how the Event Hub works for you.</p>
                </div>

                <form onSubmit={submit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Event times</CardTitle>
                            <CardDescription>
                                Pick the time zone event times are shown in. VATSIM runs on zulu (UTC), so that’s the default. Times you type in, such
                                as your availability, stay in UTC.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            <Field label="Time zone" error={form.errors.timezone}>
                                <TimezoneSelect
                                    timezones={timezones}
                                    value={form.data.timezone}
                                    onChange={(e) => form.setData('timezone', e.target.value)}
                                />
                            </Field>

                            {canUseBrowserTimezone && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="self-start"
                                    onClick={() => form.setData('timezone', browserTimezone)}
                                >
                                    <LocateFixed />
                                    Use my time zone ({describeTimezone(browserTimezone)})
                                </Button>
                            )}

                            {sample && (
                                <p className="text-sm text-muted-foreground">
                                    An event at 18:00z today shows as{' '}
                                    <span className="font-medium text-foreground tabular-nums">
                                        {clockIn(form.data.timezone, sample)}
                                        {form.data.timezone === 'UTC' ? 'z' : ` ${describeTimezone(form.data.timezone)} time`}
                                    </span>
                                    .
                                </p>
                            )}
                        </CardContent>
                        <CardFooter className="justify-end">
                            <Button type="submit" disabled={!form.isDirty || form.processing}>
                                Save
                            </Button>
                        </CardFooter>
                    </Card>
                </form>
            </div>
        </>
    );
}

Settings.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
