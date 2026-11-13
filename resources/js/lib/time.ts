// Clock times in the viewer's chosen time zone, for times worked out in the browser. Most
// times arrive preformatted by App\Services\DisplayTime. Zone names always come from it too:
// browsers name most zones differently ("GMT+9" where it says "JST"), so none are made here.

const clocks = new Map<string, Intl.DateTimeFormat>();

const clockFormat = (timezone: string) => {
    if (!clocks.has(timezone)) {
        clocks.set(timezone, new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit', hourCycle: 'h23', timeZone: timezone }));
    }

    return clocks.get(timezone)!;
};

/**
 * HH:MM in the given time zone, a number of minutes after an ISO 8601 moment.
 */
export const clockIn = (timezone: string, iso: string, minutesLater = 0) =>
    clockFormat(timezone).format(new Date(new Date(iso).getTime() + minutesLater * 60_000));

/**
 * Whether times in this zone are UTC, written with a "z". The server only accepts the name "UTC"
 * (Laravel's `timezone:all` leaves out aliases like "Etc/UTC"), so that is the one name to check.
 */
export const isUtc = (timezone: string) => timezone === 'UTC';

/**
 * HH:MM in UTC with a "z", as on VATSIM: "18:00z". The same in every browser and on the server.
 */
export const zuluClock = (moment: Date | number | string) => `${clockFormat('UTC').format(new Date(moment))}z`;

/**
 * Names the zone times are shown in, for notes like "All times are in …".
 */
export const describeTimezone = (timezone: string) => (isUtc(timezone) ? 'UTC (zulu)' : timezone.replaceAll('_', ' '));
