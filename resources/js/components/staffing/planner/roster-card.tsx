import { router } from '@inertiajs/react';
import { EyeOff, GripVertical } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useConfirm } from '@/components/confirm-dialog';
import { formatWindows } from '@/components/staffing/open-interest';
import Timeline, { type TimelineItem } from '@/components/staffing/timeline';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { NativeSelect } from '@/components/ui/native-select';
import { cn } from '@/lib/utils';
import type { Controller, StaffInterest } from '@/types/staffing';
import { AddPositionRow } from './add-position-row';
import { removePosition, type DraftProps, type StaffingPageProps } from './draft';

type Candidate = Controller & { interest: StaffInterest | null };

// The positions a controller asked for: the first few as chips, the rest summed up (all are in the card's tooltip).
function PositionChips({ callsigns, visible = 3 }: { callsigns: string[]; visible?: number }) {
    const shown = callsigns.length > visible + 1 ? callsigns.slice(0, visible) : callsigns;
    const hidden = callsigns.length - shown.length;

    return (
        <span className="flex flex-wrap gap-1">
            {shown.map((callsign) => (
                <span key={callsign} className="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] leading-none">
                    {callsign}
                </span>
            ))}
            {hidden > 0 && <span className="rounded px-1 py-0.5 text-[11px] leading-none text-muted-foreground">+{hidden} more</span>}
        </span>
    );
}

export function RosterCard({ occurrence, controllers, interests, draft, update, newKey }: StaffingPageProps & DraftProps) {
    const confirm = useConfirm();
    const { durationMinutes } = occurrence;
    const locked = occurrence.rosterArchived;
    const [extraIds, setExtraIds] = useState<number[]>([]);
    const [dragging, setDragging] = useState<Candidate | null>(null);

    // Everyone who showed interest, plus any other controller staff pulled in by hand.
    const candidates = useMemo<Candidate[]>(
        () => [
            ...interests.map((interest) => ({ ...interest.controller, interest })),
            ...controllers.filter((controller) => extraIds.includes(controller.id)).map((controller) => ({ ...controller, interest: null })),
        ],
        [interests, controllers, extraIds],
    );
    const others = controllers.filter((controller) => !candidates.some((candidate) => candidate.id === controller.id));
    const rostered = new Set(draft.roster.map((slot) => slot.controller.id));

    // A dropped controller starts where they were dropped (not before they're available) and
    // runs until that availability window ends or the next slot on that position begins. The
    // window is the one dropped into, else the next one after the drop, else their last.
    const assign = (position: number, minute: number, userId: string) => {
        const candidate = candidates.find((item) => item.id === Number(userId));

        if (!candidate) {
            return;
        }

        const windows = candidate.interest?.windows ?? [];
        const availability = windows.find((item) => minute < item.untilOffset) ?? windows.at(-1);
        const from = availability?.fromOffset ?? 0;
        const until = availability?.untilOffset ?? durationMinutes;
        const start = Math.max(from, Math.min(minute, until - 15));
        const nextOnRow = draft.roster.filter((slot) => slot.position === position && slot.start > start).map((slot) => slot.start);
        const end = Math.min(until, ...nextOnRow);
        const { interest, ...controller } = candidate;

        update((current) => ({ ...current, roster: [...current.roster, { key: newKey(), id: null, position, controller, start, end }] }));
    };

    const items: TimelineItem[] = draft.roster.map((slot) => ({
        id: slot.key,
        rowId: slot.position,
        start: slot.start,
        end: slot.end,
        label: slot.controller.name,
    }));

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    Roster
                    {occurrence.rosterPublished ? <Badge>Published</Badge> : <Badge variant="outline">Not published</Badge>}
                    {locked && <Badge variant="secondary">Archived</Badge>}
                </CardTitle>
                <CardDescription>
                    {locked
                        ? 'The event has ended, so this roster is kept as a record and can no longer change.'
                        : 'Add the positions you want staffed below the timeline, so controllers can pick them when showing interest. Then drag a controller onto a position to roster them for their availability, and drag the roster entry or its edges to adjust it. You can keep editing the roster until the event ends.'}
                </CardDescription>
                {!locked && occurrence.rosterPublished && (
                    <CardAction>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.delete(`/occurrences/${occurrence.id}/roster/publish`, { preserveScroll: true })}
                        >
                            <EyeOff />
                            Unpublish
                        </Button>
                    </CardAction>
                )}
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                {!locked && (
                    <div className="flex flex-col gap-2">
                        <h3 className="text-sm font-medium">Controllers</h3>
                        {candidates.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nobody has shown interest yet
                                {occurrence.interestState === 'upcoming' ? `. Interest opens ${occurrence.interestOpensAt}.` : '.'}
                            </p>
                        ) : (
                            <ul className="flex flex-wrap gap-2">
                                {candidates.map((candidate) => (
                                    <li
                                        key={candidate.id}
                                        draggable
                                        onDragStart={(event) => {
                                            event.dataTransfer.setData('text/plain', String(candidate.id));
                                            event.dataTransfer.effectAllowed = 'copy';
                                            setDragging(candidate);
                                        }}
                                        onDragEnd={() => setDragging(null)}
                                        title={
                                            candidate.interest
                                                ? [candidate.interest.callsigns.join(', '), candidate.interest.notes].filter(Boolean).join('\n')
                                                : undefined
                                        }
                                        className={cn(
                                            'flex w-64 cursor-grab items-start gap-1.5 rounded-lg bg-card py-2 pr-3 pl-1.5 text-sm ring-1 ring-foreground/10 hover:ring-primary/50 active:cursor-grabbing',
                                            rostered.has(candidate.id) && 'opacity-60',
                                        )}
                                    >
                                        <GripVertical className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                        <span className="flex min-w-0 flex-1 flex-col gap-1">
                                            <span className="flex items-baseline justify-between gap-2">
                                                <span className="truncate font-medium">{candidate.name}</span>
                                                {candidate.rating && (
                                                    <span className="shrink-0 text-xs text-muted-foreground">{candidate.rating}</span>
                                                )}
                                            </span>
                                            {candidate.interest ? (
                                                <>
                                                    <span className="font-mono text-xs text-muted-foreground tabular-nums">
                                                        {formatWindows(candidate.interest.windows)}
                                                    </span>
                                                    <PositionChips callsigns={candidate.interest.callsigns} />
                                                </>
                                            ) : (
                                                <span className="text-xs text-muted-foreground">No interest shown</span>
                                            )}
                                            {candidate.interest?.notes && (
                                                <span className="truncate text-xs text-muted-foreground">“{candidate.interest.notes}”</span>
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                        {others.length > 0 && (
                            <div className="max-w-xs">
                                <NativeSelect
                                    aria-label="Add another controller"
                                    value=""
                                    onChange={(e) => e.target.value && setExtraIds((ids) => [...ids, Number(e.target.value)])}
                                >
                                    <option value="">Add a controller who didn’t show interest…</option>
                                    {others.map((controller) => (
                                        <option key={controller.id} value={controller.id}>
                                            {controller.name} {controller.rating && `(${controller.rating})`}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </div>
                        )}
                    </div>
                )}

                <Timeline
                    noun="roster entry"
                    startsAt={occurrence.startsAt}
                    duration={durationMinutes}
                    readOnly={locked}
                    allowRowChange
                    rows={draft.positions.map((position) => ({
                        id: position.key,
                        label: position.callsign,
                        detail: position.frequency,
                        highlighted: dragging?.interest?.positionIds.includes(position.key) ?? false,
                    }))}
                    items={items}
                    highlight={dragging?.interest?.windows.map((item) => ({ start: item.fromOffset, end: item.untilOffset })) ?? null}
                    emptyHint={locked ? undefined : 'Drop a controller here'}
                    onExternalDrop={assign}
                    onRemoveRow={(key) => removePosition({ draft, update, newKey }, key, confirm)}
                    footer={locked ? undefined : <AddPositionRow draft={draft} update={update} newKey={newKey} />}
                    onChange={(item, position, start, end) =>
                        update((current) => ({
                            ...current,
                            roster: current.roster.map((slot) => (slot.key === item.id ? { ...slot, position, start, end } : slot)),
                        }))
                    }
                    onDelete={(item) => update((current) => ({ ...current, roster: current.roster.filter((slot) => slot.key !== item.id) }))}
                />
            </CardContent>
        </Card>
    );
}
