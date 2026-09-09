import type { ManagedEvent } from '@/types/events';

export function eventTime(iso: string, timezone = 'UTC') {
    return new Intl.DateTimeFormat('en-GB', {
        timeZone: timezone,
        dateStyle: 'medium',
        timeStyle: 'short',
        hour12: false,
    }).format(new Date(iso));
}

export function updateEventStart<
    T extends Pick<ManagedEvent, 'local_start' | 'monthly_week'>,
>(data: T, localStart: string): T {
    const day = Number(localStart.slice(8, 10));
    const dateChanged =
        localStart.slice(0, 10) !== data.local_start.slice(0, 10);

    return {
        ...data,
        local_start: localStart,
        monthly_week:
            dateChanged && day ? Math.ceil(day / 7) : data.monthly_week,
    };
}

export function recurrenceLabel(
    event: Pick<
        ManagedEvent,
        'local_start' | 'recurrence' | 'recurrence_interval' | 'monthly_week'
    >,
) {
    if (event.recurrence === 'none') return 'One-time event';
    if (!event.local_start) return 'Choose a start date to set the weekday';
    const date = new Date(event.local_start.slice(0, 10) + 'T12:00:00Z');
    if (Number.isNaN(date.getTime())) return 'Choose a start date';
    const weekday = new Intl.DateTimeFormat('en-GB', {
        weekday: 'long',
        timeZone: 'UTC',
    }).format(date);
    if (event.recurrence === 'weekly') {
        if (event.recurrence_interval === 1) return 'Every ' + weekday;
        if (event.recurrence_interval === 2) return 'Every other ' + weekday;
        return 'Every ' + event.recurrence_interval + ' weeks on ' + weekday;
    }
    const ordinal: Record<number, string> = {
        1: 'first',
        2: 'second',
        3: 'third',
        4: 'fourth',
        5: 'fifth',
        '-1': 'last',
    };
    return (
        'The ' +
        ordinal[event.monthly_week ?? 1] +
        ' ' +
        weekday +
        ' every ' +
        (event.recurrence_interval === 1
            ? 'month'
            : event.recurrence_interval + ' months')
    );
}
