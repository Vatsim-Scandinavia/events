import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { draftFrom, unbookedBy, type Draft, type StaffingPageProps } from '@/components/staffing/planner/draft';
import { RosterCard } from '@/components/staffing/planner/roster-card';
import { SaveBar } from '@/components/staffing/planner/save-bar';
import { SettingsCard } from '@/components/staffing/planner/settings-card';
import { ShiftsCard } from '@/components/staffing/planner/shifts-card';
import { useUnsavedChangesGuard } from '@/components/staffing/planner/use-unsaved-changes-guard';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout';

export default function Staffing(props: StaffingPageProps) {
    const { event, settings, occurrence } = props;

    return (
        <>
            <Head title={`Staffing · ${event.name}`} />

            <div className="mx-auto flex max-w-6xl flex-col gap-8 px-4 py-8 sm:px-6">
                <div className="flex flex-col gap-3">
                    <Button variant="ghost" size="sm" className="-ml-2.5 self-start" asChild>
                        <Link href={`/events/${event.id}`}>
                            <ArrowLeft />
                            Back to event
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold tracking-tight">Staffing · {event.name}</h1>
                    <p className="text-sm text-muted-foreground">
                        {occurrence.date} · {occurrence.time}
                        {occurrence.hasEnded && ' · Ended'}
                    </p>
                </div>

                <SettingsCard {...props} />

                {settings.mode === 'none' ? (
                    <p className="rounded-xl px-4 py-3 text-sm text-muted-foreground ring-1 ring-foreground/10">
                        Choose pre-defined shifts or open interest above to start adding positions.
                    </p>
                ) : (
                    <Planner {...props} />
                )}
            </div>
        </>
    );
}

Staffing.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;

function Planner(props: StaffingPageProps) {
    const { event, settings, occurrence } = props;
    const saved = useMemo(() => draftFrom(props), [props.positions, props.roster]); // eslint-disable-line react-hooks/exhaustive-deps
    const [draft, setDraft] = useState(saved);
    const [error, setError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);
    const nextKey = useRef(-1);
    const newKey = () => nextKey.current--;

    // Start again from what's saved whenever the page reloads with fresh data (e.g. after saving).
    useEffect(() => setDraft(saved), [saved]);

    const mode = settings.mode;
    const dirty = useMemo(() => {
        const comparable = (value: Draft) =>
            JSON.stringify({
                positions: value.positions,
                shifts: mode === 'shifts' ? value.shifts.map(({ booking, ...shift }) => shift) : [],
                roster: mode === 'open_interest' ? value.roster.map(({ controller, ...slot }) => ({ ...slot, user: controller.id })) : [],
            });

        return comparable(draft) !== comparable(saved);
    }, [draft, saved, mode]);

    useUnsavedChangesGuard(dirty && !saving);

    const update = (change: (draft: Draft) => Draft) => {
        setError(null);
        setDraft(change);
    };

    const save = (publish = false) => {
        setSaving(true);
        router.put(
            `/events/${event.id}/staffing/plan`,
            {
                occurrence_id: occurrence.id,
                positions: draft.positions,
                ...(mode === 'shifts' && {
                    shifts: draft.shifts.map(({ id, position, start, end }) => ({ id, position, start, end })),
                }),
                ...(mode === 'open_interest' && {
                    roster: draft.roster.map(({ id, position, controller, start, end }) => ({ id, position, user_id: controller.id, start, end })),
                    publish,
                }),
            },
            {
                preserveScroll: true,
                onError: (errors) => setError(errors.plan ?? Object.values(errors)[0] ?? 'The changes could not be saved.'),
                onFinish: () => setSaving(false),
            },
        );
    };

    const unbooked = mode === 'shifts' ? unbookedBy(draft, saved) : [];

    const discard = () => {
        setError(null);
        setDraft(saved);
    };

    return (
        <>
            {mode === 'shifts' && <ShiftsCard occurrence={occurrence} draft={draft} saved={saved} update={update} newKey={newKey} />}
            {mode === 'open_interest' && <RosterCard {...props} draft={draft} update={update} newKey={newKey} />}

            <SaveBar
                dirty={dirty}
                saving={saving}
                error={error}
                onDiscard={discard}
                warning={
                    unbooked.length > 0
                        ? `Saving will unbook ${unbooked.map(({ shift, callsign }) => `${shift.booking!.controller.name} (${callsign})`).join(', ')}.`
                        : null
                }
                onSave={() => save()}
                publish={
                    mode === 'open_interest' && !occurrence.rosterArchived && !occurrence.eventDraft
                        ? { published: occurrence.rosterPublished, onSaveAndPublish: () => save(true) }
                        : undefined
                }
                liveNote={
                    mode === 'shifts'
                        ? 'Shifts become bookable when you save.'
                        : occurrence.eventDraft
                          ? 'The roster can be published once the event is.'
                          : occurrence.rosterPublished
                            ? 'The roster is published, so saved changes show to controllers straight away.'
                            : 'The roster stays hidden from controllers until you publish it.'
                }
            />
        </>
    );
}
