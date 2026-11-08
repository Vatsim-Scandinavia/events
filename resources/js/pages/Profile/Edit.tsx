import { Head, useForm } from '@inertiajs/react';
import { LocateFixed } from 'lucide-react';
import { Fragment, useSyncExternalStore, type FormEvent, type ReactNode } from 'react';
import { TimezoneSelect } from '@/components/timezone-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import AppLayout from '@/layouts/AppLayout';
import { clockIn, describeTimezone } from '@/lib/time';

// Mirrors App\Http\Controllers\ProfileController@edit.
type ProfileProps = {
    // As VATSIM knows them; read-only here.
    details: { name: string; cid: number | null; rating: string | null; subdivision: string | null };
    // Across the Hub first, then each FIR. Empty for a Pilot.
    roleGroups: { scope: string; roles: { name: string; automatic: boolean }[] }[];
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

export default function Profile({ details, roleGroups, settings, timezones }: ProfileProps) {
    const form = useForm({ timezone: settings.timezone });
    const browserTimezone = useBrowserTimezone();
    const sample = useTodayAtSixZulu();
    const canUseBrowserTimezone = browserTimezone !== null && browserTimezone !== form.data.timezone && timezones.includes(browserTimezone);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put('/profile', { preserveScroll: true });
    };

    return (
        <>
            <Head title="Profile" />

            <div className="mx-auto flex max-w-3xl flex-col gap-8 px-4 py-8 sm:px-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">Profile</h1>
                    <p className="text-muted-foreground">Who you are on the Event Hub, and how it works for you.</p>
                </div>

                <DetailsCard details={details} />
                <RolesCard roleGroups={roleGroups} />

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

Profile.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;

function DetailsCard({ details }: { details: ProfileProps['details'] }) {
    const rows: [string, ReactNode][] = [
        ['Name', details.name],
        ['CID', details.cid ?? '–'],
        ['Rating', details.rating ?? '–'],
        ['Subdivision', details.subdivision ?? 'None'],
    ];

    return (
        <Card>
            <CardHeader>
                <CardTitle>Your details</CardTitle>
                <CardDescription>These come from VATSIM and are kept up to date from there.</CardDescription>
            </CardHeader>
            <CardContent>
                <dl className="grid grid-cols-[auto_1fr] gap-x-8 gap-y-2 text-sm">
                    {rows.map(([label, value]) => (
                        <Fragment key={label}>
                            <dt className="text-muted-foreground">{label}</dt>
                            <dd className="tabular-nums">{value}</dd>
                        </Fragment>
                    ))}
                </dl>
            </CardContent>
        </Card>
    );
}

function RolesCard({ roleGroups }: { roleGroups: ProfileProps['roleGroups'] }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Your roles</CardTitle>
                <CardDescription>What you can do on the Event Hub, and where.</CardDescription>
            </CardHeader>
            <CardContent>
                {roleGroups.length === 0 ? (
                    <p className="text-sm">You’re a Pilot: you can see events and staffing, and book once you’re a Controller.</p>
                ) : (
                    <dl className="flex flex-col gap-4 text-sm">
                        {roleGroups.map((group) => (
                            <div key={group.scope} className="flex flex-col gap-1.5">
                                <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">{group.scope}</dt>
                                <dd className="flex flex-wrap gap-1.5">
                                    {group.roles.map((role) => (
                                        <Badge key={role.name} variant="secondary">
                                            {role.name}
                                            {role.automatic && (
                                                <span
                                                    className="font-normal text-muted-foreground"
                                                    title="Given automatically, from your VATSIM rating and subdivision or an Integration"
                                                >
                                                    · Automatic
                                                </span>
                                            )}
                                        </Badge>
                                    ))}
                                </dd>
                            </div>
                        ))}
                    </dl>
                )}
            </CardContent>
        </Card>
    );
}
