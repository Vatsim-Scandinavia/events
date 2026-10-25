// The repeat intervals of App\Enums\RepeatInterval that fit a first date, for the form's picker,
// and helpers for days as YYYY-MM-DD in UTC. The dates themselves are worked out on the server
// (see useSchedulePreview).

export type RepeatInterval = 'none' | 'weekly' | 'biweekly' | 'monthly_weekday' | 'monthly_last_weekday';

// EventSchedule::MAX_DATES
export const MAX_DATES = 52;

const DAY = 86_400_000;
const ordinals = ['first', 'second', 'third', 'fourth'];

export const parseDay = (day: string) => new Date(`${day}T00:00:00Z`);
export const toDay = (date: Date | number) => new Date(date).toISOString().slice(0, 10);
export const addDays = (day: string, days: number) => toDay(parseDay(day).getTime() + days * DAY);

const weekdayName = (day: string) => parseDay(day).toLocaleDateString('en-GB', { weekday: 'long', timeZone: 'UTC' });
const daysInMonth = (year: number, month: number) => new Date(Date.UTC(year, month + 1, 0)).getUTCDate();

/** Whether the interval makes sense for a first date (see RepeatInterval::fits). */
export function fits(repeats: RepeatInterval, first: string): boolean {
    const date = parseDay(first);
    const day = date.getUTCDate();

    if (repeats === 'monthly_weekday') {
        return day <= 28;
    }

    if (repeats === 'monthly_last_weekday') {
        return day > daysInMonth(date.getUTCFullYear(), date.getUTCMonth()) - 7;
    }

    return true;
}

/** The intervals offered for a first date, in words. */
export function options(first: string): { value: RepeatInterval; label: string }[] {
    const weekday = weekdayName(first);
    const all: { value: RepeatInterval; label: string }[] = [
        { value: 'none', label: 'Doesn’t repeat' },
        { value: 'weekly', label: `Every week on ${weekday}` },
        { value: 'biweekly', label: `Every 2 weeks on ${weekday}` },
        { value: 'monthly_weekday', label: `Monthly on the ${ordinals[Math.ceil(parseDay(first).getUTCDate() / 7) - 1]} ${weekday}` },
        { value: 'monthly_last_weekday', label: `Monthly on the last ${weekday}` },
    ];

    return all.filter((option) => fits(option.value, first));
}

/** The closest interval that still fits after the first date changes. */
export function fitting(repeats: RepeatInterval, first: string): RepeatInterval {
    if (fits(repeats, first)) {
        return repeats;
    }

    const other = repeats === 'monthly_weekday' ? 'monthly_last_weekday' : 'monthly_weekday';

    return fits(other, first) ? other : 'none';
}

export const formatDay = (day: string) =>
    parseDay(day).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' });

export const formatShortDay = (day: string) =>
    parseDay(day).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', timeZone: 'UTC' });

export const daysBetween = (from: string, to: string) => Math.round((parseDay(to).getTime() - parseDay(from).getTime()) / DAY);
