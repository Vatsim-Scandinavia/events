import { useHttp } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { formatShortDay, type RepeatInterval } from '@/lib/repeat';
import { plural } from '@/lib/utils';
import type { EditableDate, EditableEvent } from '@/types/event';

export type RepeatEnds = 'never' | 'count' | 'until';

export type ScheduleInput = {
    starts_at: string;
    ends_at: string;
    timezone: string;
    repeats: RepeatInterval;
    repeat_ends: RepeatEnds;
    repeat_count: number;
    repeat_until: string;
};

// Mirrors EventManagementController@preview: each date the schedule gives the event, with the
// existing date that moves there (if any), and the existing dates saving would delete.
export type SchedulePreview = { dates: { date: string; id: number | null }[]; removed: number[] };

// 'invalid' when saving would be refused (the errors say why), null when the preview couldn't be loaded.
type PreviewResult = SchedulePreview | 'invalid' | null;

// The bookings, interest and roster slots on the given dates, or null when there are none.
export function describeStaffing(dates: EditableDate[]): string | null {
    const bookings = dates.reduce((sum, date) => sum + date.bookings, 0);
    const interests = dates.reduce((sum, date) => sum + date.interests, 0);
    const roster = dates.reduce((sum, date) => sum + date.roster, 0);
    const parts = [
        bookings && plural(bookings, 'shift booking'),
        interests && `interest from ${plural(interests, 'controller')}`,
        roster && plural(roster, 'roster slot'),
    ].filter(Boolean) as string[];

    return parts.length ? new Intl.ListFormat('en-GB').format(parts) : null;
}

export const removedDates = (event: EditableEvent, preview: SchedulePreview) => event.dates.filter((date) => preview.removed.includes(date.id));

/**
 * The dates the form's schedule gives the event, worked out by App\Staffing\EventSchedule on
 * the server, so the preview is always what saving does. It refreshes shortly after the
 * schedule changes; `latest()` resolves to the preview for the schedule as it is now, for
 * use right before saving.
 */
export function useSchedulePreview(event: EditableEvent | null, input: ScheduleInput) {
    const http = useHttp<Record<string, never>, SchedulePreview>({});
    const pending = useRef<{ key: string; result: Promise<PreviewResult> } | null>(null);
    const url = event ? `/events/${event.id}/schedule` : '/events/schedule';
    const key = JSON.stringify(input);

    http.transform(() => input);

    // One request per schedule: asking again for the same schedule waits on the same answer.
    const latest = (): Promise<PreviewResult> => {
        if (pending.current?.key === key) {
            return pending.current.result;
        }

        http.cancel();
        const result: Promise<PreviewResult> = http.post(url).then(
            (preview) => preview ?? 'invalid',
            () => {
                // Cancelled by a newer schedule, or the request failed: ask again next time.
                if (pending.current?.result === result) {
                    pending.current = null;
                }

                return null;
            },
        );
        pending.current = { key, result };

        return result;
    };

    useEffect(() => {
        const timer = setTimeout(latest, 300);

        return () => clearTimeout(timer);
        // Only a change to the schedule needs a new preview.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [key]);

    return {
        preview: http.hasErrors ? null : http.response,
        errors: http.errors as Record<string, string | undefined>,
        latest,
    };
}

// Warns about staffing a schedule change would move or delete. The dates themselves are listed in the Cancel card.
export function ScheduleWarnings({ event, preview }: { event: EditableEvent; preview: SchedulePreview }) {
    const moved = preview.dates.flatMap(({ date, id }) => {
        const from = event.dates.find((existing) => existing.id === id);

        return from && from.date !== date && describeStaffing([from]) ? [{ date, from }] : [];
    });
    const lost = removedDates(event, preview).filter((date) => describeStaffing([date]));

    if (moved.length === 0 && lost.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-2 rounded-lg bg-muted/40 p-3 ring-1 ring-foreground/10">
            {moved.map(({ date, from }) => (
                <p key={date} className="text-xs text-muted-foreground">
                    {formatShortDay(from.date)} moves to {formatShortDay(date)}, with its {describeStaffing([from])}.
                </p>
            ))}
            {lost.map((date) => (
                <p key={date.id} className="text-xs text-warning">
                    {formatShortDay(date.date)} is no longer in the schedule, so its {describeStaffing([date])} will be deleted.
                </p>
            ))}
        </div>
    );
}
